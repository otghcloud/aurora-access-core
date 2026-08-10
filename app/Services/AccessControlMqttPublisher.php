<?php

namespace App\Services;

use App\Models\Access\Area;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Sensor;
use App\Services\AccessControl\AccessControlSettingsRepository;
use App\Services\AccessControl\AccessOutputOrchestrator;
use App\Services\AccessControl\AutolockSettingsResolver;
use App\Support\AccessControlMqttTopic;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Throwable;

class AccessControlMqttPublisher
{
    public function __construct(
        private readonly AutolockSettingsResolver $autolockSettingsResolver,
        private readonly AccessControlSettingsRepository $settings,
    ) {}

    /**
     * @return array{lock_power:int|null,autolock_enabled:int,autolock_duration:int,ts:string}
     */
    public function buildReaderStatePayload(Reader $reader, ?int $knownLockPower = null): array
    {
        $lockPower = $knownLockPower ?? $this->resolveLockPower($reader);
        $autolock = $this->autolockSettingsResolver->resolveForReader($reader);

        return [
            'lock_power' => $lockPower,
            'autolock_enabled' => (bool) ($autolock['enabled'] ?? false) ? 1 : 0,
            'autolock_duration' => max(0, (int) ($autolock['duration'] ?? 0)),
            'ts' => now()->toIso8601String(),
        ];
    }

    public function publishReaderState(Reader $reader, ?int $knownLockPower = null): void
    {
        $payload = $this->buildReaderStatePayload($reader, $knownLockPower);

        $topic = AccessControlMqttTopic::stateTopic($reader);
        $connectionName = $this->resolvePublisherConnection();

        try {
            $this->publishPayload($connectionName, $topic, $payload, $reader);
        } catch (Throwable $e) {
            Log::warning('Failed to publish reader MQTT state. Retrying with a fresh connection.', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            try {
                $this->publishPayload($connectionName, $topic, $payload, $reader);

                Log::info('mqtt.state.publish_retry_succeeded', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $topic,
                ]);
            } catch (Throwable $retryError) {
                Log::warning('Failed to publish reader MQTT state after retry.', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'topic' => $topic,
                    'payload' => $payload,
                    'error' => $retryError->getMessage(),
                    'exception' => $retryError,
                ]);
            }
        }
    }

    public function publishSensorState(Sensor $sensor): void
    {
        $payload = [
            'state' => $sensor->state ? 1 : 0,
            'ts' => now()->toIso8601String(),
            'sensor_id' => $sensor->id,
            'sensor_identifier' => $sensor->identifier,
            'area_id' => $sensor->area_id,
        ];

        $topic = $this->sensorStateTopic($sensor);
        $connectionName = $this->resolvePublisherConnection();

        try {
            $this->publishPayload($connectionName, $topic, $payload, $sensor);
        } catch (Throwable $e) {
            Log::warning('Failed to publish sensor MQTT state.', [
                'sensor_id' => $sensor->id,
                'sensor_identifier' => $sensor->identifier,
                'topic' => $topic,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function publishTransientEvent(Reader $reader, array $payload): void
    {
        $payload['ts'] = now()->toIso8601String();

        $topic = AccessControlMqttTopic::eventsTopic($reader);
        $connectionName = $this->resolvePublisherConnection();

        try {
            $this->publishPayload($connectionName, $topic, $payload, $reader, false);
        } catch (Throwable $e) {
            Log::warning('mqtt.event.publish_failed', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'topic' => $topic,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{topic:string,retained:bool,payload:array<string,mixed>|null}|null
     */
    public function readRetainedReaderState(Reader $reader, int $timeoutSeconds = 2): ?array
    {
        $connectionName = $this->resolvePublisherConnection();
        $topic = AccessControlMqttTopic::stateTopic($reader);
        $config = $this->resolveConnectionConfig($connectionName);
        $settings = (array) Arr::get($config, 'connection_settings', []);
        $settings['socket_timeout'] = min(max(1, $timeoutSeconds), 2);
        $config['connection_settings'] = $settings;

        $mqtt = $this->makeClient($config);
        $result = null;
        $loopStartedAt = microtime(true);

        try {
            $mqtt->subscribe($topic, function (string $receivedTopic, string $message, bool $retained) use (&$result, $mqtt): void {
                $decoded = json_decode($message, true);
                $result = [
                    'topic' => $receivedTopic,
                    'retained' => $retained,
                    'payload' => is_array($decoded) ? $decoded : null,
                ];

                $mqtt->interrupt();
            }, 0);

            while ($result === null && (microtime(true) - $loopStartedAt) < $timeoutSeconds) {
                $mqtt->loopOnce($loopStartedAt, false);
            }
        } finally {
            if ($mqtt->isConnected()) {
                try {
                    $mqtt->disconnect();
                } catch (Throwable $disconnectError) {
                    Log::debug('MQTT retained state probe disconnect failed.', [
                        'connection' => $connectionName ?: 'default',
                        'error' => $disconnectError->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function publishPayload(?string $connectionName, string $topic, array $payload, Reader $reader, bool $retain = true): void
    {
        $config = $this->resolveConnectionConfig($connectionName);
        $mqtt = $this->makeClient($config);

        try {
            $mqtt->publish($topic, (string) json_encode($payload, JSON_UNESCAPED_SLASHES), 0, $retain);
        } finally {
            if ($mqtt->isConnected()) {
                try {
                    $mqtt->disconnect();
                } catch (Throwable $disconnectError) {
                    Log::debug('MQTT state publisher disconnect after publish failed.', [
                        'connection' => $connectionName ?: 'default',
                        'error' => $disconnectError->getMessage(),
                    ]);
                }
            }
        }

        Log::info($retain ? 'mqtt.state.published' : 'mqtt.event.published', [
            'reader_id' => $reader->id,
            'reader_identifier' => $reader->identifier,
            'topic' => $topic,
            'payload' => $payload,
            'retained' => $retain,
            'connection' => $connectionName ?: 'default',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveConnectionConfig(?string $connectionName): array
    {
        $resolvedConnection = $connectionName;

        if ($resolvedConnection === null || trim($resolvedConnection) === '') {
            $defaultConnection = config('mqtt-client.default_connection', 'default');
            $resolvedConnection = is_string($defaultConnection) && trim($defaultConnection) !== '' ? trim($defaultConnection) : 'default';
        }

        $config = config("mqtt-client.connections.{$resolvedConnection}");

        if (! is_array($config)) {
            throw new \RuntimeException("MQTT connection [{$resolvedConnection}] is not configured.");
        }

        return $config;
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private function makeClient(array $config): MqttClient
    {
        $host = (string) Arr::get($config, 'host');
        $port = (int) Arr::get($config, 'port', 1883);
        $protocol = (string) Arr::get($config, 'protocol', MqttClient::MQTT_3_1);
        $cleanSession = (bool) Arr::get($config, 'use_clean_session', true);
        $repositoryClass = Arr::get($config, 'repository');
        $loggingEnabled = (bool) Arr::get($config, 'enable_logging', true);
        $logChannel = Arr::get($config, 'log_channel');
        $configuredClientId = Arr::get($config, 'client_id');

        $repository = is_string($repositoryClass) && $repositoryClass !== ''
            ? app($repositoryClass)
            : app(MemoryRepository::class);

        $logger = null;

        if ($loggingEnabled) {
            $logger = app('log');

            if (is_string($logChannel) && $logChannel !== '') {
                $logger = $logger->channel($logChannel);
            }
        }

        $clientId = is_string($configuredClientId) && trim($configuredClientId) !== ''
            ? trim($configuredClientId).'-'.Str::lower(Str::random(8))
            : null;

        $client = new MqttClient($host, $port, $clientId, $protocol, $repository, $logger);
        $client->connect($this->buildConnectionSettings((array) Arr::get($config, 'connection_settings', [])), $cleanSession);

        return $client;
    }

    /**
     * @param  array<string,mixed>  $config
     */
    private function buildConnectionSettings(array $config): ConnectionSettings
    {
        return (new ConnectionSettings)
            ->setConnectTimeout((int) Arr::get($config, 'connect_timeout', 60))
            ->setSocketTimeout((int) Arr::get($config, 'socket_timeout', 5))
            ->setResendTimeout((int) Arr::get($config, 'resend_timeout', 10))
            ->setKeepAliveInterval((int) Arr::get($config, 'keep_alive_interval', 10))
            ->setUsername(Arr::get($config, 'auth.username'))
            ->setPassword(Arr::get($config, 'auth.password'))
            ->setUseTls((bool) Arr::get($config, 'tls.enabled', false))
            ->setTlsSelfSignedAllowed((bool) Arr::get($config, 'tls.allow_self_signed_certificate', false))
            ->setTlsVerifyPeer((bool) Arr::get($config, 'tls.verify_peer', true))
            ->setTlsVerifyPeerName((bool) Arr::get($config, 'tls.verify_peer_name', true))
            ->setTlsCertificateAuthorityFile(Arr::get($config, 'tls.ca_file'))
            ->setTlsCertificateAuthorityPath(Arr::get($config, 'tls.ca_path'))
            ->setTlsClientCertificateFile(Arr::get($config, 'tls.client_certificate_file'))
            ->setTlsClientCertificateKeyFile(Arr::get($config, 'tls.client_certificate_key_file'))
            ->setTlsClientCertificateKeyPassphrase(Arr::get($config, 'tls.client_certificate_key_passphrase'))
            ->setTlsAlpn(Arr::get($config, 'tls.alpn'))
            ->setLastWillTopic(Arr::get($config, 'last_will.topic'))
            ->setLastWillMessage(Arr::get($config, 'last_will.message'))
            ->setLastWillQualityOfService((int) Arr::get($config, 'last_will.quality_of_service', MqttClient::QOS_AT_MOST_ONCE))
            ->setRetainLastWill((bool) Arr::get($config, 'last_will.retain', false))
            ->setReconnectAutomatically((bool) Arr::get($config, 'auto_reconnect.enabled', false))
            ->setMaxReconnectAttempts((int) Arr::get($config, 'auto_reconnect.max_reconnect_attempts', 3))
            ->setDelayBetweenReconnectAttempts((int) Arr::get($config, 'auto_reconnect.delay_between_reconnect_attempts', 0));
    }

    private function resolveLockPower(Reader $reader): ?int
    {
        try {
            $locked = app(AccessOutputOrchestrator::class)->readLockState($reader);

            return $locked === null ? null : ($locked ? 1 : 0);
        } catch (Throwable $e) {
            Log::warning('Failed to read lock power while publishing MQTT state.', [
                'reader_id' => $reader->id,
                'reader_identifier' => $reader->identifier,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sensorStateTopic(Sensor $sensor): string
    {
        $area = $sensor->area;

        if (! $area instanceof Area) {
            throw new \RuntimeException('Sensor must be assigned to an area for MQTT topic generation.');
        }

        return AccessControlMqttTopic::areaBaseTopic($area).'/sensor/'.$sensor->identifier;
    }

    private function resolvePublisherConnection(): ?string
    {
        $connection = $this->settings->get('mqtt_publisher_connection', 'publisher');
        $normalized = is_string($connection) ? trim($connection) : '';

        return $normalized !== '' ? $normalized : null;
    }
}
