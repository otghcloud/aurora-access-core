<?php

namespace OTGH\AccessControl\Core\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;
use OTGH\AccessControl\Core\Services\AccessControl\HealthCheckRegistry;
use Throwable;

class AccessControlHealthService
{
    public function __construct(
        private readonly HealthCheckRegistry $healthCheckRegistry,
        private readonly AccessControlSettingsRepository $settings,
    ) {}

    public const HEALTH_STATUS_CACHE_KEY = 'access_control.health.last_status';

    public const MQTT_SYNC_STATUS_CACHE_KEY = 'access_control.mqtt_sync.last_status';

    /**
     * @return array{
     *     ok: bool,
     *     generated_at: string,
     *     queue_connection: string,
     *     queue_name: string,
     *     redis_connection: string,
     *     critical_failures: int,
     *     warnings: int,
     *     checks: array<int, array{name:string,status:string,details:string}>,
     *     mqtt_sync: array<string,mixed>|null
     * }
     */
    public function generate(?string $queueOverride = null, ?string $readerIdentifier = null): array
    {
        $checks = [];
        $failedChecks = 0;

        $queueConnection = (string) config('queue.default');
        $queueName = (string) ($queueOverride ?: config('queue.connections.redis.queue', 'default'));
        $redisConnection = (string) config('queue.connections.redis.connection', 'default');
        $supervisorPermissionGated = false;
        $monitorProcessOk = false;
        $serialMonitorProcessOk = true;
        $modbusMonitorProcessOk = false;
        $workerProcessOk = false;

        $status = $queueConnection === 'redis' ? 'PASS' : 'FAIL';
        $detail = sprintf('queue.default=%s (expected redis)', $queueConnection);
        $checks[] = $this->makeCheck('Queue driver', $status, $detail);
        if ($status === 'FAIL') {
            $failedChecks++;
        }

        try {
            $pong = Redis::connection($redisConnection)->command('PING');
            $checks[] = $this->makeCheck('Redis ping', 'PASS', sprintf('connection=%s response=%s', $redisConnection, is_scalar($pong) ? (string) $pong : 'PONG'));
        } catch (Throwable $e) {
            $checks[] = $this->makeCheck('Redis ping', 'FAIL', $e->getMessage());
            $failedChecks++;
        }

        try {
            $depth = (int) Redis::connection($redisConnection)->llen('queues:'.$queueName);
            $depthStatus = $depth > 100 ? 'WARN' : 'PASS';
            $checks[] = $this->makeCheck('Queue depth', $depthStatus, sprintf('queue=%s pending=%d', $queueName, $depth));
        } catch (Throwable $e) {
            $checks[] = $this->makeCheck('Queue depth', 'FAIL', $e->getMessage());
            $failedChecks++;
        }

        $failedTable = (string) config('queue.failed.table', 'failed_jobs');

        try {
            if (! Schema::hasTable($failedTable)) {
                $checks[] = $this->makeCheck('Failed jobs table', 'WARN', sprintf('table %s not found', $failedTable));
            } else {
                $failedCount = (int) DB::table($failedTable)->count();
                $failedStatus = $failedCount > 0 ? 'WARN' : 'PASS';
                $checks[] = $this->makeCheck('Failed jobs', $failedStatus, sprintf('table=%s count=%d', $failedTable, $failedCount));

                if ($failedCount > 0) {
                    $latest = DB::table($failedTable)->orderByDesc('failed_at')->first();
                    if ($latest !== null) {
                        $checks[] = $this->makeCheck(
                            'Latest failed job',
                            'WARN',
                            sprintf('failed_at=%s uuid=%s', (string) ($latest->failed_at ?? 'n/a'), (string) ($latest->uuid ?? 'n/a'))
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            $checks[] = $this->makeCheck('Failed jobs table', 'FAIL', $e->getMessage());
            $failedChecks++;
        }

        $mqttHost = trim((string) config('mqtt-client.connections.monitor.host', config('mqtt-client.connections.default.host', '')));
        $mqttPort = (int) config('mqtt-client.connections.monitor.port', config('mqtt-client.connections.default.port', 1883));
        $mqttProtocol = (string) config('mqtt-client.connections.monitor.protocol', config('mqtt-client.connections.default.protocol', 'unknown'));
        $mqttPublisherConnection = (string) $this->settings->get('mqtt_publisher_connection', 'publisher');
        $checks[] = $this->makeCheck(
            'MQTT broker config',
            $mqttHost !== '' ? 'PASS' : 'FAIL',
            $mqttHost !== ''
                ? sprintf('host=%s port=%d protocol=%s publisher_connection=%s', $mqttHost, $mqttPort, $mqttProtocol, $mqttPublisherConnection)
                : 'MQTT host is not configured'
        );
        if ($mqttHost === '') {
            $failedChecks++;
        }

        $supervisorOutput = $this->runSupervisorStatus();
        if ($supervisorOutput === null || trim($supervisorOutput) === '') {
            $checks[] = $this->makeCheck('Supervisor status', 'WARN', 'Unable to execute supervisorctl status');
        } elseif (stripos($supervisorOutput, 'command not found') !== false) {
            $checks[] = $this->makeCheck('Supervisor status', 'WARN', 'supervisorctl command not found');
        } elseif (
            stripos($supervisorOutput, 'permission denied') !== false
            || stripos($supervisorOutput, 'a password is required') !== false
            || stripos($supervisorOutput, 'not in the sudoers file') !== false
        ) {
            $supervisorPermissionGated = true;
        } else {
            foreach (['access-control-queue', 'access-control-mqtt-monitor', 'access-control-modbus-monitor'] as $program) {
                $programLines = $this->findProgramLines($supervisorOutput, $program);

                if ($programLines === []) {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'WARN', 'Program not found in supervisorctl status (name may differ)');

                    continue;
                }

                $allRunning = true;
                foreach ($programLines as $line) {
                    if (stripos($line, ' RUNNING ') === false && ! str_ends_with(trim($line), ' RUNNING')) {
                        $allRunning = false;
                        break;
                    }
                }

                if ($allRunning) {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'PASS', 'RUNNING');
                } else {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'FAIL', implode(' | ', $programLines));
                    $failedChecks++;
                }
            }

            foreach (Reader::query()->orderBy('identifier', 'asc')->get() as $reader) {
                $program = 'access-control-serial-'.$reader->identifier;
                $programLines = $this->findProgramLines($supervisorOutput, $program);

                if ($programLines === []) {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'WARN', 'Program not found in supervisorctl status (name may differ)');
                    $serialMonitorProcessOk = false;

                    continue;
                }

                $allRunning = true;
                foreach ($programLines as $line) {
                    if (stripos($line, ' RUNNING ') === false && ! str_ends_with(trim($line), ' RUNNING')) {
                        $allRunning = false;
                        break;
                    }
                }

                if ($allRunning) {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'PASS', 'RUNNING');
                } else {
                    $checks[] = $this->makeCheck('Supervisor '.$program, 'FAIL', implode(' | ', $programLines));
                    $failedChecks++;
                    $serialMonitorProcessOk = false;
                }
            }
        }

        $monitorMatches = $this->runShellCommand('pgrep -fa "artisan app:monitor-reader-push" 2>/dev/null');
        if ($monitorMatches !== null && trim($monitorMatches) !== '') {
            $count = count(array_filter(array_map('trim', explode("\n", trim($monitorMatches)))));
            $checks[] = $this->makeCheck('Monitor process match', 'PASS', sprintf('pgrep matches=%d', $count));
            $monitorProcessOk = true;
        } else {
            $checks[] = $this->makeCheck('Monitor process match', 'WARN', 'No app:monitor-reader-push process found via pgrep');
        }

        $modbusMonitorMatches = $this->runShellCommand('pgrep -fa "artisan app:monitor-modbus-sources" 2>/dev/null');
        if ($modbusMonitorMatches !== null && trim($modbusMonitorMatches) !== '') {
            $count = count(array_filter(array_map('trim', explode("\n", trim($modbusMonitorMatches)))));
            $checks[] = $this->makeCheck('Modbus monitor process match', 'PASS', sprintf('pgrep matches=%d', $count));
            $modbusMonitorProcessOk = true;
        } else {
            $checks[] = $this->makeCheck('Modbus monitor process match', 'WARN', 'No app:monitor-modbus-sources process found via pgrep');
        }

        $workerMatches = $this->runShellCommand('pgrep -fa "artisan queue:work redis" 2>/dev/null');
        if ($workerMatches !== null && trim($workerMatches) !== '') {
            $count = count(array_filter(array_map('trim', explode("\n", trim($workerMatches)))));
            $checks[] = $this->makeCheck('Queue worker match', 'PASS', sprintf('pgrep matches=%d', $count));
            $workerProcessOk = true;
        } else {
            $checks[] = $this->makeCheck('Queue worker match', 'WARN', 'No redis queue:work process found via pgrep');
        }

        foreach (Reader::query()->orderBy('identifier', 'asc')->get() as $reader) {
            $inputFormat = strtolower((string) data_get($reader->config, 'general.input_format', 'wiegand'));
            if ($inputFormat !== 'wiegand') {
                continue;
            }

            $serialMatches = $this->runShellCommand(sprintf('pgrep -fa %s 2>/dev/null', escapeshellarg('artisan app:monitor-serial-reader '.$reader->identifier)));
            if ($serialMatches !== null && trim($serialMatches) !== '') {
                $count = count(array_filter(array_map('trim', explode("\n", trim($serialMatches)))));
                $checks[] = $this->makeCheck('Serial reader process '.$reader->identifier, 'PASS', sprintf('pgrep matches=%d', $count));
            } else {
                $checks[] = $this->makeCheck('Serial reader process '.$reader->identifier, 'WARN', 'No matching app:monitor-serial-reader process found via pgrep');
                $serialMonitorProcessOk = false;
            }

            $devicePath = (string) data_get($reader->config, 'wiegand.device', '/dev/'.$reader->identifier);
            $deviceReadable = $devicePath !== '' && file_exists($devicePath) && is_readable($devicePath);
            $checks[] = $this->makeCheck(
                'Serial reader device '.$reader->identifier,
                $deviceReadable ? 'PASS' : 'FAIL',
                sprintf('device=%s readable=%s', $devicePath !== '' ? $devicePath : '/dev/'.$reader->identifier, $deviceReadable ? 'yes' : 'no')
            );

            if (! $deviceReadable) {
                $failedChecks++;
            }
        }

        if ($supervisorPermissionGated) {
            $checks[] = $this->makeCheck(
                'Supervisor status',
                $monitorProcessOk && $modbusMonitorProcessOk && $workerProcessOk && $serialMonitorProcessOk ? 'PASS' : 'WARN',
                $monitorProcessOk && $modbusMonitorProcessOk && $workerProcessOk && $serialMonitorProcessOk
                    ? 'supervisorctl is permission-gated; required processes are running per pgrep'
                    : 'supervisorctl is permission-gated and one or more required processes are not running'
            );
        }

        foreach ($this->healthCheckRegistry->runAll($this, $readerIdentifier) as $externalCheck) {
            $checks[] = $externalCheck;

            if (($externalCheck['status'] ?? 'WARN') === 'FAIL') {
                $failedChecks++;
            }
        }

        $probe = $this->runMqttProbe($readerIdentifier);
        $checks[] = $this->makeCheck($probe['name'], $probe['status'], $probe['details']);
        if ($probe['status'] === 'FAIL') {
            $failedChecks++;
        }

        $syncStatus = $this->getLastMqttSyncStatus();
        $syncCheck = $this->buildMqttSyncCheck($syncStatus);
        $checks[] = $syncCheck;

        $payload = [
            'ok' => $failedChecks === 0,
            'generated_at' => now()->toIso8601String(),
            'queue_connection' => $queueConnection,
            'queue_name' => $queueName,
            'redis_connection' => $redisConnection,
            'critical_failures' => $failedChecks,
            'warnings' => count(array_filter($checks, fn (array $check): bool => $check['status'] === 'WARN')),
            'checks' => $checks,
            'mqtt_sync' => $syncStatus,
        ];

        Cache::forever(self::HEALTH_STATUS_CACHE_KEY, $payload);

        return $payload;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLastHealthStatus(): ?array
    {
        $status = Cache::get(self::HEALTH_STATUS_CACHE_KEY);

        return is_array($status) ? $status : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLastMqttSyncStatus(): ?array
    {
        $status = Cache::get(self::MQTT_SYNC_STATUS_CACHE_KEY);

        return is_array($status) ? $status : null;
    }

    /**
     * @return array{name:string,status:string,details:string}
     */
    private function makeCheck(string $name, string $status, string $details): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'details' => $details,
        ];
    }

    /**
     * @return array{name:string,status:string,details:string}
     */
    private function runMqttProbe(?string $readerIdentifier = null): array
    {
        $reader = $this->resolveProbeReader($readerIdentifier);

        if ($reader === null) {
            return $this->makeCheck('MQTT state probe', 'WARN', 'No reader available for MQTT state probe');
        }

        try {
            $publisher = app(AccessControlMqttPublisher::class);
            $expectedPayload = $publisher->buildReaderStatePayload($reader);
            $publisher->publishReaderState($reader, Arr::get($expectedPayload, 'lock_power'));
            $observed = $publisher->readRetainedReaderState($reader, 2);

            if ($observed === null) {
                return $this->makeCheck('MQTT state probe', 'FAIL', sprintf('No retained payload observed for %s', $reader->mqttStateTopic()));
            }

            $observedPayload = Arr::get($observed, 'payload');

            if (! is_array($observedPayload)) {
                return $this->makeCheck('MQTT state probe', 'FAIL', sprintf('Retained payload on %s was not valid JSON', $reader->mqttStateTopic()));
            }

            $matches = Arr::get($observedPayload, 'autolock_enabled') === Arr::get($expectedPayload, 'autolock_enabled')
                && Arr::get($observedPayload, 'autolock_duration') === Arr::get($expectedPayload, 'autolock_duration');

            return $this->makeCheck(
                'MQTT state probe',
                $matches ? 'PASS' : 'FAIL',
                sprintf(
                    'reader=%s topic=%s retained=%s expected_autolock=%s observed_autolock=%s expected_duration=%s observed_duration=%s',
                    $reader->identifier,
                    $reader->mqttStateTopic(),
                    Arr::get($observed, 'retained', false) ? 'yes' : 'no',
                    (string) Arr::get($expectedPayload, 'autolock_enabled', 'n/a'),
                    (string) Arr::get($observedPayload, 'autolock_enabled', 'n/a'),
                    (string) Arr::get($expectedPayload, 'autolock_duration', 'n/a'),
                    (string) Arr::get($observedPayload, 'autolock_duration', 'n/a')
                )
            );
        } catch (Throwable $e) {
            return $this->makeCheck('MQTT state probe', 'FAIL', $e->getMessage());
        }
    }

    /**
     * @param  array<string,mixed>|null  $syncStatus
     * @return array{name:string,status:string,details:string}
     */
    private function buildMqttSyncCheck(?array $syncStatus): array
    {
        if ($syncStatus === null) {
            return $this->makeCheck('MQTT drift sync schedule', 'WARN', 'No reconciliation run has been recorded yet');
        }

        $generatedAt = Arr::get($syncStatus, 'generated_at');
        $republished = (int) Arr::get($syncStatus, 'republished', 0);
        $failures = (int) Arr::get($syncStatus, 'failures', 0);
        $dryRun = (bool) Arr::get($syncStatus, 'dry_run', false);
        $readersChecked = (int) Arr::get($syncStatus, 'readers_checked', 0);

        if (! is_string($generatedAt) || trim($generatedAt) === '') {
            return $this->makeCheck('MQTT drift sync schedule', 'WARN', 'Last reconciliation summary is missing a generated_at timestamp');
        }

        try {
            $lastRun = now()->parse($generatedAt);
        } catch (Throwable) {
            return $this->makeCheck('MQTT drift sync schedule', 'WARN', sprintf('Invalid reconciliation timestamp: %s', $generatedAt));
        }

        $minutesSinceRun = $lastRun->diffInMinutes(now());
        $status = $failures > 0 ? 'FAIL' : ($minutesSinceRun > 15 ? 'WARN' : 'PASS');

        return $this->makeCheck(
            'MQTT drift sync schedule',
            $status,
            sprintf(
                'last_run=%s age_minutes=%d readers_checked=%d republished=%d failures=%d dry_run=%s',
                $lastRun->format('Y-m-d H:i:s'),
                $minutesSinceRun,
                $readersChecked,
                $republished,
                $failures,
                $dryRun ? 'yes' : 'no'
            )
        );
    }

    private function resolveProbeReader(?string $readerIdentifier = null): ?Reader
    {
        if (is_string($readerIdentifier) && trim($readerIdentifier) !== '') {
            return Reader::query()->where('identifier', trim($readerIdentifier))->first();
        }

        return Reader::query()->orderBy('id', 'asc')->first();
    }

    private function runShellCommand(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $output = shell_exec($command);

        return is_string($output) ? $output : null;
    }

    private function runSupervisorStatus(): ?string
    {
        $sudoOutput = $this->runShellCommand('sudo -n supervisorctl status 2>&1');

        if ($sudoOutput !== null
            && stripos($sudoOutput, 'a password is required') === false
            && stripos($sudoOutput, 'not in the sudoers file') === false
            && stripos($sudoOutput, 'command not found') === false
        ) {
            return $sudoOutput;
        }

        return $this->runShellCommand('supervisorctl status 2>&1');
    }

    /**
     * @return array<int,string>
     */
    private function findProgramLines(string $statusOutput, string $programName): array
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($statusOutput))));

        $matches = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, $programName)) {
                $matches[] = $line;
            }
        }

        return $matches;
    }
}
