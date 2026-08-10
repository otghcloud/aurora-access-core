<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

$sharedConnectionSettings = static fn (): array => [
    'tls' => [
        'enabled' => env('MQTT_TLS_ENABLED', false),
        'allow_self_signed_certificate' => env('MQTT_TLS_ALLOW_SELF_SIGNED_CERT', false),
        'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'ca_file' => env('MQTT_TLS_CA_FILE'),
        'ca_path' => env('MQTT_TLS_CA_PATH'),
        'client_certificate_file' => env('MQTT_TLS_CLIENT_CERT_FILE'),
        'client_certificate_key_file' => env('MQTT_TLS_CLIENT_CERT_KEY_FILE'),
        'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_CERT_KEY_PASSPHRASE'),
        'alpn' => env('MQTT_TLS_ALPN'),
    ],
    'auth' => [
        'username' => env('MQTT_AUTH_USERNAME'),
        'password' => env('MQTT_AUTH_PASSWORD'),
    ],
    'last_will' => [
        'topic' => env('MQTT_LAST_WILL_TOPIC'),
        'message' => env('MQTT_LAST_WILL_MESSAGE'),
        'quality_of_service' => env('MQTT_LAST_WILL_QUALITY_OF_SERVICE', 0),
        'retain' => env('MQTT_LAST_WILL_RETAIN', false),
    ],
    'connect_timeout' => env('MQTT_CONNECT_TIMEOUT', 60),
    'socket_timeout' => env('MQTT_SOCKET_TIMEOUT', 5),
    'resend_timeout' => env('MQTT_RESEND_TIMEOUT', 10),
    'keep_alive_interval' => env('MQTT_KEEP_ALIVE_INTERVAL', 10),
    'auto_reconnect' => [
        'enabled' => env('MQTT_AUTO_RECONNECT_ENABLED', false),
        'max_reconnect_attempts' => env('MQTT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS', 3),
        'delay_between_reconnect_attempts' => env('MQTT_AUTO_RECONNECT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 0),
    ],
];

$makeConnection = static fn (?string $clientId, array $overrides = []): array => array_replace_recursive([
    'host' => env('MQTT_HOST'),
    'port' => env('MQTT_PORT', 1883),
    'protocol' => env('MQTT_PROTOCOL', MqttClient::MQTT_3_1),
    'client_id' => $clientId,
    'use_clean_session' => env('MQTT_CLEAN_SESSION', true),
    'enable_logging' => env('MQTT_ENABLE_LOGGING', true),
    'log_channel' => env('MQTT_LOG_CHANNEL', null),
    'repository' => MemoryRepository::class,
    'connection_settings' => $sharedConnectionSettings(),
], $overrides);

return [

    /*
    |--------------------------------------------------------------------------
    | Default MQTT Connection
    |--------------------------------------------------------------------------
    |
    | This setting defines the default MQTT connection returned when requesting
    | a connection without name from the facade.
    |
    */

    'default_connection' => 'default',

    // Dedicated connection used by the long-running monitor command.
    'monitor_connection' => env('MQTT_MONITOR_CONNECTION', 'monitor'),

    /*
    |--------------------------------------------------------------------------
    | MQTT Connections
    |--------------------------------------------------------------------------
    |
    | These are the MQTT connections used by the application. You can also open
    | an individual connection from the application itself, but all connections
    | defined here can be accessed via name conveniently.
    |
    */

    'connections' => [

        'default' => $makeConnection(env('MQTT_CLIENT_ID')),

        'monitor' => $makeConnection(
            env('MQTT_MONITOR_CLIENT_ID', ((string) env('MQTT_CLIENT_ID', 'ag-access')).'-monitor')
        ),

        'publisher' => $makeConnection(env('MQTT_PUBLISHER_CLIENT_ID')),

    ],

];
