<?php

namespace OTGH\AccessControl\Core\Services\HomeAssistant;

use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\AccessControl\LockStateStore;
use OTGH\AccessControl\Core\Support\AccessControlMqttTopic;

class HAIntegrationService
{
    public function __construct(
        private readonly LockStateStore $lockStateStore,
        private readonly AutolockSettingsResolver $autolockSettingsResolver,
    ) {}

    /**
     * Build Home Assistant device object for a lock
     */
    public function buildHADeviceLock(Lock $lock): array
    {
        return [
            'identifiers' => [
                ['aurora', 'lock_'.$lock->id],
            ],
            'name' => $lock->name,
            'manufacturer' => 'Aurora',
            'model' => 'Access Lock',
            'hw_version' => '1.0',
            'sw_version' => config('app.version'),
            'area' => $lock->area->name,
        ];
    }

    /**
     * Build Home Assistant entity object for a lock
     */
    public function buildHAEntityLock(Lock $lock): array
    {
        return [
            'type' => 'lock',
            'object_id' => 'lock_'.str($lock->identifier)->slug(),
            'unique_id' => 'aurora_lock_'.$lock->id,
            'name' => $lock->name,
            'device' => $this->buildHADeviceLock($lock),
            'icon' => 'mdi:lock',
            'entity_category' => null,
            'enabled_by_default' => true,
            'availability_topic' => 'aurora/locks/'.$lock->id.'/available',
            'state_topic' => 'aurora/locks/'.$lock->id.'/state',
            'command_topic' => 'aurora/locks/'.$lock->id.'/command',
            'payload_lock' => 'lock',
            'payload_unlock' => 'unlock',
            'state_locked' => 'locked',
            'state_unlocked' => 'unlocked',
            'optimistic' => false,
            'retain' => true,
        ];
    }

    /**
     * Build Home Assistant device object for a sensor
     */
    public function buildHADeviceSensor(Sensor $sensor): array
    {
        return [
            'identifiers' => [
                ['aurora', 'sensor_'.$sensor->id],
            ],
            'name' => $sensor->name,
            'manufacturer' => 'Aurora',
            'model' => 'Access Sensor',
            'hw_version' => '1.0',
            'sw_version' => config('app.version'),
            'area' => $sensor->area->name,
        ];
    }

    /**
     * Build Home Assistant entity object for a binary sensor (door contact, etc)
     */
    public function buildHAEntityBinarySensor(Sensor $sensor): array
    {
        return [
            'type' => 'binary_sensor',
            'object_id' => 'sensor_'.str($sensor->identifier)->slug(),
            'unique_id' => 'aurora_sensor_'.$sensor->id,
            'name' => $sensor->name,
            'device' => $this->buildHADeviceSensor($sensor),
            'icon' => $this->getIconForSensor($sensor),
            'device_class' => $this->getDeviceClassForSensor($sensor),
            'entity_category' => 'diagnostic',
            'enabled_by_default' => true,
            'availability_topic' => 'aurora/sensors/'.$sensor->id.'/available',
            'state_topic' => 'aurora/sensors/'.$sensor->id.'/state',
            'payload_on' => 'on',
            'payload_off' => 'off',
            'json_attributes_topic' => 'aurora/sensors/'.$sensor->id.'/attributes',
            'expire_after' => 300,
        ];
    }

    /**
     * Build Home Assistant device object for a light
     */
    public function buildHADeviceLight(Light $light): array
    {
        return [
            'identifiers' => [
                ['aurora', 'light_'.$light->id],
            ],
            'name' => $light->name,
            'manufacturer' => 'Aurora',
            'model' => 'Access Light',
            'hw_version' => '1.0',
            'sw_version' => config('app.version'),
            'area' => $light->area->name,
        ];
    }

    /**
     * Build Home Assistant entity object for a light
     */
    public function buildHAEntityLight(Light $light): array
    {
        $entity = [
            'type' => 'light',
            'object_id' => 'light_'.str($light->identifier)->slug(),
            'unique_id' => 'aurora_light_'.$light->id,
            'name' => $light->name,
            'device' => $this->buildHADeviceLight($light),
            'icon' => 'mdi:lightbulb',
            'entity_category' => null,
            'enabled_by_default' => true,
            'availability_topic' => 'aurora/lights/'.$light->id.'/available',
            'state_topic' => 'aurora/lights/'.$light->id.'/state',
            'command_topic' => 'aurora/lights/'.$light->id.'/command',
            'payload_on' => 'on',
            'payload_off' => 'off',
            'state_on' => 'on',
            'state_off' => 'off',
            'optimistic' => false,
            'retain' => true,
        ];

        // Add brightness support if light supports it
        if (data_get($light->config, 'features.brightness', false)) {
            $entity['brightness_state_topic'] = 'aurora/lights/'.$light->id.'/brightness';
            $entity['brightness_command_topic'] = 'aurora/lights/'.$light->id.'/brightness/set';
            $entity['brightness_scale'] = 100;
        }

        // Add color support if light supports it
        if (data_get($light->config, 'features.color', false)) {
            $entity['hs_state_topic'] = 'aurora/lights/'.$light->id.'/hs';
            $entity['hs_command_topic'] = 'aurora/lights/'.$light->id.'/hs/set';
        }

        return $entity;
    }

    /**
     * Get icon for sensor based on name or type
     */
    protected function getIconForSensor(Sensor $sensor): string
    {
        $name = strtolower($sensor->name);

        if (str_contains($name, 'door')) {
            return 'mdi:door';
        } elseif (str_contains($name, 'window')) {
            return 'mdi:window-closed';
        } elseif (str_contains($name, 'motion')) {
            return 'mdi:motion-sensor';
        } elseif (str_contains($name, 'emergency')) {
            return 'mdi:alarm-light';
        } else {
            return 'mdi:sensor';
        }
    }

    /**
     * Get device class for sensor
     */
    protected function getDeviceClassForSensor(Sensor $sensor): ?string
    {
        $name = strtolower($sensor->name);

        if (str_contains($name, 'door')) {
            return 'door';
        } elseif (str_contains($name, 'window')) {
            return 'window';
        } elseif (str_contains($name, 'motion')) {
            return 'motion';
        } elseif (str_contains($name, 'smoke')) {
            return 'smoke';
        } elseif (str_contains($name, 'occupancy')) {
            return 'occupancy';
        } else {
            return null;
        }
    }

    /**
     * Build discovery config for MQTT discovery
     * This is used by the HA integration to auto-discover devices
     */
    public function buildMQTTDiscoveryPayload(string $entityType, array $entity): array
    {
        $discoveryTopic = 'homeassistant/'.$entity['type'].'/'.$entity['device']['identifiers'][0][1].'/config';

        return [
            'topic' => $discoveryTopic,
            'payload' => $entity,
            'retain' => true,
            'qos' => 1,
        ];
    }

    /**
     * Build status response for HA polling
     * Used by GET /api/ha/status to provide current state
     */
    public function buildAreaStatusForHA(Area $area): array
    {
        return [
            'id' => (string) $area->id,
            'name' => $area->name,
            'identifier' => $area->identifier,
            'updated_at' => $area->updated_at->toIso8601String(),
            'devices' => [
                'locks' => $area->locks->map(fn (Lock $lock) => $this->buildLockStatusForHA($lock, $area))->toArray(),
                'sensors' => $area->sensors->map(fn (Sensor $sensor) => $this->buildSensorStatusForHA($sensor, $area))->toArray(),
                'lights' => $area->lights()->with('area')->get()->map(fn (Light $light) => $this->buildLightStatusForHA($light))->toArray(),
            ],
        ];
    }

    /**
     * Build lock status for HA
     */
    public function buildLockStatusForHA(Lock $lock, ?Area $area = null): array
    {
        $state = $this->lockStateStore->forLock($lock);
        $autolock = $this->autolockSettingsResolver->resolveForAreaAndLock($area ?? $lock->area, $lock);

        return [
            'id' => (string) $lock->id,
            'unique_id' => 'aurora_lock_'.$lock->id,
            'name' => $lock->name,
            'identifier' => $lock->identifier,
            'state' => $state['state'],
            'available' => true,
            'confidence' => $state['confidence'],
            'updated_at' => $state['updated_at'] ?? $lock->updated_at->toIso8601String(),
            'state_source' => $state['source'],
            'state_topic' => AccessControlMqttTopic::lockStateTopic($lock),
            'autolock' => [
                'enabled' => $autolock['enabled'],
                'duration_seconds' => min(3600, max(0, $autolock['duration'])),
                'source' => $autolock['source'],
            ],
            'bindings' => $this->bindingDiagnostics($lock),
        ];
    }

    /**
     * Build sensor status for HA
     */
    public function buildSensorStatusForHA(Sensor $sensor, ?Area $area = null): array
    {
        return [
            'id' => (string) $sensor->id,
            'unique_id' => 'aurora_sensor_'.$sensor->id,
            'name' => $sensor->name,
            'identifier' => $sensor->identifier,
            'state' => $sensor->state ? 'on' : 'off',
            'state_raw' => $sensor->state,
            'available' => true,
            'device_class' => $this->getDeviceClassForSensor($sensor),
            'updated_at' => $sensor->updated_at->toIso8601String(),
            'state_topic' => AccessControlMqttTopic::sensorStateTopic($sensor),
            'bindings' => $this->bindingDiagnostics($sensor),
        ];
    }

    /**
     * Build light status for HA
     */
    public function buildLightStatusForHA(Light $light): array
    {
        $data = [
            'id' => (string) $light->id,
            'unique_id' => 'aurora_light_'.$light->id,
            'name' => $light->name,
            'identifier' => $light->identifier,
            'state' => $light->state ? 'on' : 'off',
            'available' => true,
            'updated_at' => $light->updated_at->toIso8601String(),
            'state_topic' => AccessControlMqttTopic::lightStateTopic($light),
            'capabilities' => [
                'brightness' => $light->supportsBrightness(),
                'color' => $light->supportsColor(),
            ],
            'bindings' => $this->bindingDiagnostics($light),
        ];

        if (data_get($light->config, 'features.brightness', false)) {
            $data['brightness'] = $light->brightness ?? 100;
        }

        if (data_get($light->config, 'features.color', false)) {
            $data['color'] = $light->color ?? '#ffffff';
        }

        return $data;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function bindingDiagnostics(Lock|Sensor|Light $device): array
    {
        return $device->adapterBindings()
            ->with('source')
            ->orderBy('id')
            ->get()
            ->map(fn ($binding): array => [
                'id' => (int) $binding->id,
                'source_id' => (int) $binding->source_id,
                'source_type' => $binding->source?->type,
                'adapter_type' => (string) $binding->adapter_type,
                'channel' => (string) $binding->channel,
                'action_key' => $binding->actionKeyName(),
                'direction' => (string) $binding->direction,
                'enabled' => (bool) $binding->enabled,
                'signal_reversed' => (bool) $binding->signal_reversed,
            ])
            ->values()
            ->all();
    }
}
