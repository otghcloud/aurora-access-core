<?php

namespace OTGH\AccessControl\Core\Support;

use LogicException;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;

class AccessControlMqttTopic
{
    public static function lockStateTopic(Lock $lock): string
    {
        return self::deviceBaseTopic($lock->area, 'locks', (int) $lock->id).'/state';
    }

    public static function lockCommandTopic(Lock $lock): string
    {
        return self::deviceBaseTopic($lock->area, 'locks', (int) $lock->id).'/command';
    }

    public static function sensorStateTopic(Sensor $sensor): string
    {
        return self::deviceBaseTopic($sensor->area, 'sensors', (int) $sensor->id).'/state';
    }

    public static function lightStateTopic(Light $light): string
    {
        return self::deviceBaseTopic($light->area, 'lights', (int) $light->id).'/state';
    }

    public static function lightCommandTopic(Light $light): string
    {
        return self::deviceBaseTopic($light->area, 'lights', (int) $light->id).'/command';
    }

    public static function deviceCommandWildcardTopic(): string
    {
        return self::topicPrefix().'/v1/areas/+/+/+/command';
    }

    private static function topicPrefix(): string
    {
        $configured = self::trimmedSetting('mqtt_base_topic', 'access_control');
        $prefix = trim($configured, '/');

        return $prefix !== '' ? $prefix : 'access_control';
    }

    private static function deviceBaseTopic(?Area $area, string $deviceType, int $deviceId): string
    {
        if (! $area instanceof Area) {
            throw new LogicException('A device must be assigned to an area for MQTT topic generation.');
        }

        return self::topicPrefix().'/v1/areas/'.$area->id.'/'.$deviceType.'/'.$deviceId;
    }

    private static function trimmedSetting(string $key, string $default): string
    {
        $settings = app(AccessControlSettingsRepository::class);
        $configured = trim((string) $settings->get($key, $default));

        return $configured !== '' ? $configured : $default;
    }
}
