<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReaderEvent;
use App\Jobs\PublishReaderState;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\AccessControlSettingsRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionManager;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

#[Signature('app:monitor-reader-push {--connection= : Named MQTT connection from config/mqtt-client.php} {--qos=0 : MQTT QoS level}')]
#[Description('Monitor access-control MQTT command topics and dispatch reader actions')]
class MonitorReaderPushRequests extends Command
{
    /** @var array<int,int|null> */
    private array $lastPushValueByReaderId = [];

    /** @var array<int,float> */
    private array $lastPushDispatchAtByReaderId = [];

    public function handle(): int
    {
        $configuredMonitorConnection = config('mqtt-client.monitor_connection');
        $connection = $this->option('connection')
            ?: (is_string($configuredMonitorConnection) && trim($configuredMonitorConnection) !== ''
                ? trim($configuredMonitorConnection)
                : null);
        $qos = max(0, min(2, (int) $this->option('qos')));

        $subscriptions = $this->buildSubscriptions();

        if ($subscriptions->isEmpty()) {
            $this->warn('No readers available for MQTT command subscriptions.');

            return self::FAILURE;
        }

        $this->logEvent('info', 'monitor.start', [
            'topic_count' => count($subscriptions),
            'mqtt_connection' => $connection ?: 'default',
            'qos' => $qos,
            'queue_connection' => config('queue.default'),
        ]);

        foreach ($subscriptions as $topic => $readersForTopic) {
            $this->logEvent('info', 'monitor.subscription', [
                'topic' => $topic,
                'reader_ids' => $readersForTopic->pluck('id')->values()->all(),
                'reader_identifiers' => $readersForTopic->pluck('identifier')->values()->all(),
            ]);
        }

        $this->publishInitialReaderState($subscriptions);

        while (true) {
            try {
                $mqtt = MQTT::connection($connection);

                foreach ($subscriptions as $topic => $readersForTopic) {
                    $mqtt->subscribe(
                        $topic,
                        function (string $topic, string $message, bool $retained) use ($readersForTopic) {
                            $this->logEvent('debug', 'mqtt.message.received', [
                                'topic' => $topic,
                                'retained' => $retained,
                                'payload_preview' => substr($message, 0, 300),
                            ]);

                            if ($retained) {
                                $this->logEvent('debug', 'mqtt.message.ignored_retained', ['topic' => $topic]);

                                return;
                            }

                            $this->handleTopicMessage($topic, $message, $readersForTopic);
                        },
                        $qos
                    );
                }

                $this->logEvent('info', 'mqtt.loop.start', ['connection' => $connection ?: 'default']);

                $mqtt->loop(true);
            } catch (Throwable $e) {
                // Ensure the next loop iteration gets a brand-new client instance.
                try {
                    app(ConnectionManager::class)->disconnect($connection);
                } catch (Throwable $disconnectError) {
                    Log::debug('Reader MQTT monitor disconnect cleanup failed.', [
                        'connection' => $connection ?: 'default',
                        'error' => $disconnectError->getMessage(),
                    ]);
                }

                Log::warning('Reader MQTT monitor error, retrying: '.$e->getMessage(), ['exception' => $e]);
                $this->warn('MQTT monitor error, reconnecting in 5s: '.$e->getMessage());
                sleep(5);
            }
        }
    }

    /**
     * @return Collection<string, Collection<int, Reader>>
     */
    private function buildSubscriptions(): Collection
    {
        $subscriptions = collect();

        foreach (Reader::query()->get() as $reader) {
            $topic = $reader->mqttCommandTopic();
            $existing = $subscriptions->get($topic, collect());
            $subscriptions->put($topic, $existing->push($reader));
        }

        return $subscriptions;
    }

    /**
     * @param  Collection<int, Reader>  $readersForTopic
     */
    private function handleTopicMessage(string $topic, string $message, Collection $readersForTopic): void
    {
        $decoded = json_decode($message, true);

        if (! is_array($decoded)) {
            $this->logEvent('debug', 'mqtt.message.ignored_non_json', ['topic' => $topic]);

            return;
        }

        foreach ($readersForTopic as $reader) {
            $commandPayload = $this->extractCommandPayload($reader, $decoded);

            if ($commandPayload === null) {
                $this->logEvent('debug', 'mqtt.command.ignored_payload_shape', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $topic,
                ]);

                continue;
            }

            $this->handleAutoLockCommand($reader, $commandPayload, $topic);
            $this->handlePushRequestCommand($reader, $commandPayload, $topic);
        }
    }

    /**
     * @param  array<string,mixed>  $decoded
     */
    private function handleAutoLockCommand(Reader $reader, array $decoded, string $topic): void
    {
        $hasEnabled = array_key_exists('autolock_enabled', $decoded);
        $hasDuration = array_key_exists('autolock_duration', $decoded);

        if (! $hasEnabled && ! $hasDuration) {
            return;
        }

        $area = $reader->area;

        if ($area === null) {
            $this->logEvent('debug', 'auto_lock.command.ignored_reader_without_area', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
            ]);

            return;
        }

        $currentEnabled = (bool) data_get($area->config, 'locking.autolock_enabled', false);
        $currentDuration = max(0, (int) data_get($area->config, 'locking.autolock_duration', 0));

        $incomingEnabled = $currentEnabled;

        if ($hasEnabled) {
            $parsedEnabled = $this->toBinaryInt($decoded['autolock_enabled']);

            if ($parsedEnabled === null) {
                $this->logEvent('debug', 'auto_lock.command.ignored_invalid_enabled', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $topic,
                    'value' => $decoded['autolock_enabled'],
                ]);
            } else {
                $incomingEnabled = ($parsedEnabled === 1);
            }
        }

        $incomingDuration = $currentDuration;

        if ($hasDuration) {
            if (is_numeric($decoded['autolock_duration'])) {
                $incomingDuration = max(0, (int) $decoded['autolock_duration']);
            } else {
                $this->logEvent('debug', 'auto_lock.command.ignored_invalid_duration', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $topic,
                    'value' => $decoded['autolock_duration'],
                ]);
            }
        }

        if ($incomingEnabled === $currentEnabled && $incomingDuration === $currentDuration) {
            $this->logEvent('debug', 'auto_lock.command.ignored_unchanged', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
                'autolock_enabled' => $currentEnabled,
                'autolock_duration' => $currentDuration,
            ]);

            return;
        }

        $config = is_array($area->config) ? $area->config : [];
        data_set($config, 'locking.autolock_enabled', $incomingEnabled);
        data_set($config, 'locking.autolock_duration', $incomingDuration);

        $area->config = $config;
        $area->save();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area->id,
            'access_lock_id' => $area->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'area',
            'origin_id' => $area->id,
            'origin_label' => $area->name,
            'granted' => true,
            'status' => 'mqtt_autolock_updated',
            'reason' => 'Auto-lock settings updated via MQTT command.',
            'metadata' => [
                'source' => 'mqtt',
                'event' => 'auto_lock_command',
                'topic' => $topic,
                'area_id' => $area->id,
                'area_identifier' => $area->identifier,
                'autolock_enabled' => $incomingEnabled,
                'autolock_duration' => $incomingDuration,
                'previous_autolock_enabled' => $currentEnabled,
                'previous_autolock_duration' => $currentDuration,
            ],
            'ip_address' => null,
        ]);

        $this->logEvent('info', 'auto_lock.command.applied', [
            'reader_id' => $reader->id,
            'reader_identifier' => $reader->identifier,
            'area_id' => $area->id,
            'area_identifier' => $area->identifier,
            'topic' => $topic,
            'autolock_enabled' => $incomingEnabled,
            'autolock_duration' => $incomingDuration,
        ]);

        Reader::query()
            ->where('area_id', $area->id)
            ->orderBy('id', 'asc')
            ->get()
            ->each(fn (Reader $areaReader) => PublishReaderState::dispatch($areaReader));
    }

    /**
     * @param  array<string,mixed>  $decoded
     */
    private function handlePushRequestCommand(Reader $reader, array $decoded, string $topic): void
    {
        if (! array_key_exists('push_requested', $decoded)) {
            return;
        }

        $pushValue = $this->toBinaryInt($decoded['push_requested']);

        if ($pushValue === null) {
            $this->logEvent('debug', 'push.command.ignored_invalid_value', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
                'value' => $decoded['push_requested'],
            ]);

            return;
        }

        $previousValue = $this->lastPushValueByReaderId[$reader->id] ?? null;
        $this->lastPushValueByReaderId[$reader->id] = $pushValue;

        $this->logEvent('info', 'push.command.value_seen', [
            'reader_id' => $reader->id,
            'reader_identifier' => $reader->identifier,
            'value' => $pushValue,
            'previous_value' => $previousValue,
            'topic' => $topic,
        ]);

        if ($pushValue !== 1) {
            $this->logEvent('debug', 'push.command.ignored_non_trigger_value', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'value' => $pushValue,
                'previous_value' => $previousValue,
            ]);

            return;
        }

        $settings = app(AccessControlSettingsRepository::class);
        $dedupeSeconds = max(0.0, (float) $settings->get('push_dedupe_seconds', 2.5));
        $now = microtime(true);
        $lastDispatchAt = $this->lastPushDispatchAtByReaderId[$reader->id] ?? null;

        if (is_float($lastDispatchAt) && ($now - $lastDispatchAt) < $dedupeSeconds) {
            $this->logEvent('debug', 'push.command.ignored_dedupe_window', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
                'seconds_since_previous' => round($now - $lastDispatchAt, 3),
                'dedupe_seconds' => $dedupeSeconds,
            ]);

            return;
        }

        $this->lastPushDispatchAtByReaderId[$reader->id] = $now;

        $this->logEvent('info', 'push.request.detected', [
            'reader_id' => $reader->id,
            'reader_identifier' => $reader->identifier,
            'topic' => $topic,
        ]);

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $reader->area_id,
            'access_lock_id' => $reader->area?->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'reader',
            'origin_id' => $reader->id,
            'origin_label' => $reader->name,
            'granted' => true,
            'status' => 'mqtt_toggle_requested',
            'reason' => 'Toggle requested via MQTT push tag.',
            'metadata' => [
                'source' => 'mqtt',
                'event' => 'toggle_request_detected',
                'topic' => $topic,
                'value' => $pushValue,
                'previous_value' => $previousValue,
            ],
            'ip_address' => null,
        ]);

        ProcessReaderEvent::dispatch(null, $reader, null, true, 'mqtt_push_request');

        $this->logEvent('info', 'lock.toggle_job.dispatched', [
            'reader_id' => $reader->id,
            'reader_identifier' => $reader->identifier,
            'queue_connection' => config('queue.default'),
        ]);
    }

    private function publishInitialReaderState(Collection $subscriptions): void
    {
        /** @var Collection<int, Reader> $readers */
        $readers = $subscriptions
            ->flatMap(fn (Collection $group): array => $group->all())
            ->unique('id')
            ->values();

        foreach ($readers as $reader) {
            PublishReaderState::dispatch($reader);
        }
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logEvent(string $level, string $event, array $context = []): void
    {
        $json = json_encode($context, JSON_UNESCAPED_SLASHES);
        $line = sprintf('[%s] %s %s', strtoupper($level), $event, $json ?: '{}');

        match ($level) {
            'warning' => $this->warn($line),
            'error' => $this->error($line),
            default => $this->line($line),
        };

        Log::log($level, $event, $context);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|null
     */
    private function extractCommandPayload(Reader $reader, array $payload): ?array
    {
        if (
            array_key_exists('push_requested', $payload)
            || array_key_exists('autolock_enabled', $payload)
            || array_key_exists('autolock_duration', $payload)
        ) {
            return $payload;
        }

        $slug = $reader->mqttReaderSlug();
        $nested = $payload[$slug] ?? null;

        if (is_array($nested)) {
            return $nested;
        }

        return null;
    }

    private function toBinaryInt(mixed $value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            if ($value === 0 || $value === 1) {
                return $value;
            }

            return null;
        }

        if (is_float($value)) {
            if ($value === 0.0 || $value === 1.0) {
                return (int) $value;
            }

            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return match ($normalized) {
                '1', 'true', 'yes', 'on' => 1,
                '0', 'false', 'no', 'off' => 0,
                default => null,
            };
        }

        return null;
    }
}
