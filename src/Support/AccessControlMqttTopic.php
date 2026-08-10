<?php

namespace OTGH\AccessControl\Core\Support;

use Illuminate\Support\Str;
use LogicException;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;

class AccessControlMqttTopic
{
    public static function areaSlug(Area $area): string
    {
        $preferred = trim((string) ($area->name ?? ''));
        $fallback = trim((string) ($area->identifier ?? ''));

        $slugSource = $preferred !== '' ? $preferred : $fallback;
        $slug = Str::slug($slugSource, '-');

        if ($slug !== '') {
            return $slug;
        }

        return 'area-'.$area->id;
    }

    public static function readerSlug(Reader $reader): string
    {
        $area = $reader->area;

        if (! $area instanceof Area) {
            throw new LogicException('Reader must be assigned to an area for MQTT topic generation.');
        }

        return self::areaSlug($area);
    }

    public static function areaBaseTopic(Area $area): string
    {
        return self::topicPrefix().'/'.self::areaSlug($area);
    }

    public static function baseTopic(Reader $reader): string
    {
        $area = $reader->area;

        if (! $area instanceof Area) {
            throw new LogicException('Reader must be assigned to an area for MQTT topic generation.');
        }

        return self::areaBaseTopic($area);
    }

    public static function commandTopic(Reader $reader): string
    {
        $suffix = self::commandSuffix();

        return self::baseTopic($reader).'/'.$suffix;
    }

    public static function stateTopic(Reader $reader): string
    {
        $suffix = self::stateSuffix();

        return self::baseTopic($reader).'/'.$suffix;
    }

    public static function eventsTopic(Reader $reader): string
    {
        $suffix = self::eventsSuffix();

        return self::baseTopic($reader).'/'.$suffix;
    }

    public static function commandSuffix(): string
    {
        return self::trimmedSetting('mqtt_command_suffix', 'cmd');
    }

    public static function stateSuffix(): string
    {
        return self::trimmedSetting('mqtt_state_suffix', 'state');
    }

    public static function eventsSuffix(): string
    {
        return self::trimmedSetting('mqtt_events_suffix', 'events');
    }

    private static function topicPrefix(): string
    {
        $configured = self::trimmedSetting('mqtt_base_topic', 'access_control');
        $prefix = trim($configured, '/');

        return $prefix !== '' ? $prefix : 'access_control';
    }

    private static function trimmedSetting(string $key, string $default): string
    {
        $settings = app(AccessControlSettingsRepository::class);
        $configured = trim((string) $settings->get($key, $default));

        return $configured !== '' ? $configured : $default;
    }
}
