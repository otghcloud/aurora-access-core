<?php

namespace OTGH\AccessControl\Core\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Jobs\ProcessLightEvent;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use OTGH\AccessControl\Core\Support\AccessControlMqttTopic;
use PhpMqtt\Client\ConnectionManager;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

#[Signature('app:monitor-device-mqtt-commands {--connection= : Named MQTT connection from config/mqtt-client.php} {--qos=0 : MQTT QoS level}')]
#[Description('Monitor v1 device-specific MQTT command topics for access control')]
class MonitorDeviceMqttCommands extends Command
{
    public function handle(): int
    {
        $configuredMonitorConnection = config('mqtt-client.monitor_connection');
        $connection = $this->option('connection')
            ?: (is_string($configuredMonitorConnection) && trim($configuredMonitorConnection) !== ''
                ? trim($configuredMonitorConnection)
                : null);
        $qos = max(0, min(2, (int) $this->option('qos')));
        $topic = AccessControlMqttTopic::deviceCommandWildcardTopic();

        app(AccessControlMqttPublisher::class)->publishAllDeviceStates();
        $this->info("Monitoring MQTT commands on {$topic}");

        while (true) {
            try {
                $mqtt = MQTT::connection($connection);
                $mqtt->subscribe($topic, function (string $receivedTopic, string $message, bool $retained): void {
                    if ($retained) {
                        return;
                    }

                    $this->handleMessage($receivedTopic, $message);
                }, $qos);
                $mqtt->loop(true);
            } catch (Throwable $e) {
                Log::warning('mqtt.device_command_monitor.failed', [
                    'connection' => $connection ?: 'default',
                    'error' => $e->getMessage(),
                ]);

                try {
                    app(ConnectionManager::class)->disconnect($connection);
                } catch (Throwable) {
                }

                $this->warn('MQTT monitor error, reconnecting in 5s: '.$e->getMessage());
                sleep(5);
            }
        }
    }

    private function handleMessage(string $topic, string $message): void
    {
        $matches = [];
        if (! preg_match('#/v1/areas/(\d+)/(locks|lights)/(\d+)/command$#', $topic, $matches)) {
            Log::debug('mqtt.device_command.ignored_topic', ['topic' => $topic]);

            return;
        }

        $payload = json_decode($message, true);
        if (! is_array($payload)) {
            Log::debug('mqtt.device_command.ignored_payload', ['topic' => $topic]);

            return;
        }

        [$areaId, $deviceType, $deviceId] = [(int) $matches[1], $matches[2], (int) $matches[3]];

        if ($deviceType === 'locks') {
            $this->handleLockCommand($areaId, $deviceId, $payload, $topic);

            return;
        }

        $this->handleLightCommand($areaId, $deviceId, $payload, $topic);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function handleLockCommand(int $areaId, int $lockId, array $payload, string $topic): void
    {
        $lock = Lock::query()->with('area')->whereKey($lockId)->where('area_id', $areaId)->first();
        if (! $lock instanceof Lock) {
            Log::warning('mqtt.device_command.unknown_lock', compact('areaId', 'lockId', 'topic'));

            return;
        }

        if (is_array($payload['autolock'] ?? null)) {
            $this->updateAutolock($lock, $payload['autolock'], $topic);

            return;
        }

        $action = $payload['action'] ?? null;
        if (! in_array($action, ['lock', 'unlock', 'toggle'], true)) {
            Log::warning('mqtt.device_command.invalid_lock_action', compact('topic', 'action'));

            return;
        }

        $reader = ReaderLockBinding::query()
            ->where('lock_id', $lock->id)
            ->where('enabled', true)
            ->with('reader')
            ->orderBy('id')
            ->first()?->reader
            ?? Reader::query()->where('area_id', $lock->area_id)->orderBy('id')->first();

        if (! $reader instanceof Reader) {
            Log::warning('mqtt.device_command.lock_without_reader', ['lock_id' => $lock->id, 'topic' => $topic]);

            return;
        }

        ProcessReaderEvent::dispatch(
            null,
            $reader,
            $action === 'lock' ? 1 : ($action === 'unlock' ? 0 : null),
            true,
            'mqtt_device_command',
        );
    }

    /**
     * @param  array<string,mixed>  $autolock
     */
    private function updateAutolock(Lock $lock, array $autolock, string $topic): void
    {
        $hasEnabled = array_key_exists('enabled', $autolock);
        $hasDuration = array_key_exists('duration_seconds', $autolock);

        if (! $hasEnabled && ! $hasDuration) {
            return;
        }

        if ($hasEnabled && ! is_bool($autolock['enabled'])) {
            Log::warning('mqtt.device_command.invalid_autolock_enabled', ['lock_id' => $lock->id, 'topic' => $topic]);

            return;
        }

        if ($hasDuration && (! is_int($autolock['duration_seconds']) || $autolock['duration_seconds'] < 0 || $autolock['duration_seconds'] > 3600)) {
            Log::warning('mqtt.device_command.invalid_autolock_duration', ['lock_id' => $lock->id, 'topic' => $topic]);

            return;
        }

        $current = app(AutolockSettingsResolver::class)
            ->resolveForAreaAndLock($lock->area, $lock);
        $config = is_array($lock->config) ? $lock->config : [];
        data_set($config, 'locking.autolock_override_enabled', $hasEnabled ? $autolock['enabled'] : $current['enabled']);
        data_set($config, 'locking.autolock_override_duration', $hasDuration ? $autolock['duration_seconds'] : $current['duration']);
        $lock->update(['config' => $config]);

        Event::create([
            'access_area_id' => $lock->area_id,
            'access_lock_id' => $lock->id,
            'origin_type' => 'mqtt',
            'origin_id' => $lock->id,
            'origin_label' => 'MQTT',
            'granted' => true,
            'status' => 'mqtt_autolock_updated',
            'reason' => 'Auto-lock settings updated via v1 MQTT device command.',
            'metadata' => ['topic' => $topic, 'autolock' => $autolock],
        ]);

        app(AccessControlMqttPublisher::class)->publishLockState($lock->fresh() ?? $lock);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function handleLightCommand(int $areaId, int $lightId, array $payload, string $topic): void
    {
        $light = Light::query()->whereKey($lightId)->where('area_id', $areaId)->first();
        $action = $payload['action'] ?? null;
        if (! $light instanceof Light || ! in_array($action, ['on', 'off', 'toggle', 'set_brightness', 'set_color'], true)) {
            Log::warning('mqtt.device_command.invalid_light_command', compact('areaId', 'lightId', 'topic', 'action'));

            return;
        }

        if ($action === 'toggle') {
            $action = $light->state ? 'off' : 'on';
        }

        $brightness = $action === 'set_brightness' && is_int($payload['brightness'] ?? null) ? $payload['brightness'] : null;
        $color = $action === 'set_color' && is_string($payload['color'] ?? null) ? $payload['color'] : null;
        if (($action === 'set_brightness' && ($brightness === null || $brightness < 0 || $brightness > 100)) || ($action === 'set_color' && $color === null)) {
            Log::warning('mqtt.device_command.invalid_light_value', ['light_id' => $light->id, 'topic' => $topic]);

            return;
        }

        ProcessLightEvent::dispatch($light->id, match ($action) {
            'set_brightness' => 'brightness',
            'set_color' => 'color',
            default => $action,
        }, $brightness, $color, 'mqtt_device_command');
    }
}
