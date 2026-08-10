<?php

namespace OTGH\AccessControl\Core\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControlHealthService;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use Throwable;

#[Signature('app:sync-reader-mqtt-state {--reader= : Limit reconciliation to a single reader identifier} {--dry-run : Report drift without republishing} {--json : Output machine-readable JSON}')]
#[Description('Reconcile retained MQTT reader state with current database and RTU values')]
class SyncReaderMqttState extends Command
{
    public function handle(AccessControlMqttPublisher $publisher): int
    {
        $jsonOutput = (bool) $this->option('json');
        $dryRun = (bool) $this->option('dry-run');
        $readerIdentifier = $this->option('reader');
        $results = [];
        $failures = 0;
        $republished = 0;
        $driftDetected = 0;

        $readers = Reader::query()
            ->when(is_string($readerIdentifier) && trim($readerIdentifier) !== '', function ($query) use ($readerIdentifier) {
                $query->where('identifier', trim($readerIdentifier));
            })
            ->orderBy('name')
            ->get();

        if ($readers->isEmpty()) {
            $message = 'No readers available for MQTT reconciliation.';

            if ($jsonOutput) {
                $this->line((string) json_encode([
                    'ok' => false,
                    'message' => $message,
                    'republished' => 0,
                    'failures' => 1,
                    'results' => [],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }

            $this->warn($message);

            return self::FAILURE;
        }

        foreach ($readers as $reader) {
            try {
                $freshReader = $reader->fresh();

                if (! $freshReader instanceof Reader) {
                    throw new \RuntimeException('Unable to reload reader from the database.');
                }

                $expected = $publisher->buildReaderStatePayload($freshReader);
                $observed = $publisher->readRetainedReaderState($freshReader, 2);
                $observedPayload = is_array(Arr::get($observed, 'payload')) ? Arr::get($observed, 'payload') : null;
                $differences = $this->diffPayloads($expected, $observedPayload);
                $inSync = $differences === [];
                $action = 'none';

                if (! $inSync) {
                    $driftDetected++;
                }

                if (! $inSync && ! $dryRun) {
                    $publisher->publishReaderState($freshReader, Arr::get($expected, 'lock_power'));
                    $republished++;
                    $action = 'republished';
                } elseif (! $inSync) {
                    $action = 'would_republish';
                }

                $result = [
                    'reader_id' => $freshReader->id,
                    'reader_identifier' => $freshReader->identifier,
                    'reader_name' => $freshReader->name,
                    'state_topic' => $freshReader->mqttStateTopic(),
                    'status' => $inSync ? 'in_sync' : 'drift_detected',
                    'action' => $action,
                    'differences' => $differences,
                    'expected' => $expected,
                    'observed' => $observedPayload,
                ];

                $results[] = $result;

                if (! $jsonOutput) {
                    $this->line(sprintf(
                        '[%s] %s (%s) topic=%s action=%s',
                        strtoupper($result['status']),
                        $freshReader->name ?: $freshReader->identifier,
                        $freshReader->identifier,
                        $freshReader->mqttStateTopic(),
                        $action
                    ));

                    if ($differences !== []) {
                        foreach ($differences as $field => $values) {
                            $this->line(sprintf(
                                '  - %s expected=%s observed=%s',
                                $field,
                                $this->stringify($values['expected']),
                                $this->stringify($values['observed'])
                            ));
                        }
                    }
                }
            } catch (Throwable $e) {
                $failures++;

                $results[] = [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'reader_name' => $reader->name,
                    'state_topic' => $reader->mqttStateTopic(),
                    'status' => 'error',
                    'action' => 'failed',
                    'differences' => [],
                    'expected' => null,
                    'observed' => null,
                    'error' => $e->getMessage(),
                ];

                Log::warning('mqtt.state.sync.failed', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $reader->mqttStateTopic(),
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                if (! $jsonOutput) {
                    $this->warn(sprintf('Failed to sync %s (%s): %s', $reader->name ?: $reader->identifier, $reader->identifier, $e->getMessage()));
                }
            }
        }

        $payload = [
            'ok' => $failures === 0,
            'generated_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'republished' => $republished,
            'failures' => $failures,
            'drift_detected' => $driftDetected,
            'readers_checked' => count($results),
            'results' => $results,
        ];

        Cache::forever(AccessControlHealthService::MQTT_SYNC_STATUS_CACHE_KEY, $payload);

        if ($jsonOutput) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failures > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();
        $this->info(sprintf(
            'MQTT reader state sync complete. republished=%d drift_detected=%d failures=%d dry_run=%s',
            $republished,
            $driftDetected,
            $failures,
            $dryRun ? 'yes' : 'no'
        ));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{lock_power:int|null,autolock_enabled:int,autolock_duration:int,ts:string}  $expected
     * @param  array<string,mixed>|null  $observed
     * @return array<string,array{expected:mixed,observed:mixed}>
     */
    private function diffPayloads(array $expected, ?array $observed): array
    {
        $fields = ['lock_power', 'autolock_enabled', 'autolock_duration'];
        $differences = [];

        foreach ($fields as $field) {
            $expectedValue = Arr::get($expected, $field);
            $observedValue = Arr::get($observed, $field);

            if ($expectedValue !== $observedValue) {
                $differences[$field] = [
                    'expected' => $expectedValue,
                    'observed' => $observedValue,
                ];
            }
        }

        return $differences;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
