<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use Illuminate\Database\Eloquent\Collection;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;

class StatusAggregatorService
{
    public function __construct(
        private readonly AutolockSettingsResolver $autolockResolver,
        private readonly LockStatusResolver $lockStatusResolver,
        private readonly AccessBindingResolver $bindingResolver,
    ) {}

    /**
     * Build hierarchical status for all areas and their hardware
     *
     * @return array<string,mixed>
     */
    public function buildFullStatus(): array
    {
        $areas = Area::query()
            ->orderBy('name')
            ->with(['readers', 'locks', 'sensors'])
            ->get();

        return [
            'areas' => $areas->map(fn (Area $area): array => $this->buildAreaStatus($area))->values()->all(),
            'timestamp' => now()->toIso8601String(),
            'health' => 'ok',
        ];
    }

    /**
     * Build status for a single area
     *
     * @return array<string,mixed>
     */
    public function buildAreaStatus(Area $area): array
    {
        return [
            'id' => (string) $area->id,
            'name' => $area->name,
            'slug' => $area->identifier,
            'readers' => $this->buildReadersList($area->readers ?? collect()),
            'locks' => $this->buildLocksList($area->locks ?? collect()),
            'sensors' => $this->buildSensorsList($area->sensors ?? collect()),
            'lights' => [], // Phase 4: Add lights
            'config' => [
                'autolock' => [
                    'enabled' => (bool) data_get($area->config, 'locking.autolock_enabled', false),
                    'duration_seconds' => (int) data_get($area->config, 'locking.autolock_duration', 0),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Build status array for readers
     *
     * @param  Collection<int, Reader>  $readers
     * @return array<int, array<string,mixed>>
     */
    private function buildReadersList(Collection $readers): array
    {
        return $readers->map(fn (Reader $reader): array => [
            'id' => (string) $reader->id,
            'name' => $reader->name,
            'identifier' => $reader->identifier,
            'area_id' => (string) $reader->area_id,
            'state' => 'online', // Phase 2.x: Add actual reader state via MQTT
            'mqtt_state_topic' => $reader->mqttStateTopic(),
            'mqtt_command_topic' => $reader->mqttCommandTopic(),
            'bound_locks' => $reader->lockBindings()
                ->where('enabled', true)
                ->pluck('lock_id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all(),
            'metadata' => $reader->metadata ?? [],
        ])->values()->all();
    }

    /**
     * Build status array for locks
     *
     * @param  Collection<int, Lock>  $locks
     * @return array<int, array<string,mixed>>
     */
    private function buildLocksList(Collection $locks): array
    {
        return $locks->map(fn (Lock $lock): array => [
            'id' => (string) $lock->id,
            'name' => $lock->name,
            'identifier' => $lock->identifier,
            'area_id' => (string) $lock->area_id,
            'is_primary' => (bool) $lock->is_primary,
            'state' => $this->resolveLockStateForStatus($lock),
            'autolock' => [
                'enabled' => (bool) data_get($lock->config, 'locking.autolock_override_enabled'),
                'duration_seconds' => (int) data_get($lock->config, 'locking.autolock_override_duration', 0),
            ],
            'metadata' => $lock->metadata ?? [],
        ])->values()->all();
    }

    /**
     * Build status array for sensors
     *
     * @param  Collection<int, Sensor>  $sensors
     * @return array<int, array<string,mixed>>
     */
    private function buildSensorsList(Collection $sensors): array
    {
        return $sensors->map(fn (Sensor $sensor): array => [
            'id' => (string) $sensor->id,
            'name' => $sensor->name,
            'identifier' => $sensor->identifier,
            'area_id' => (string) $sensor->area_id,
            'state' => (bool) $sensor->state ? 'on' : 'off',
            'state_raw' => (bool) $sensor->state,
            'updated_at' => $sensor->updated_at->toIso8601String(),
            'metadata' => $sensor->metadata ?? [],
        ])->values()->all();
    }

    /**
     * Resolve lock state for status endpoint
     *
     * @return array<string,mixed>
     */
    private function resolveLockStateForStatus(Lock $lock): array
    {
        // This would ideally query actual lock state from adapters
        // For now, return a simple state representation
        return [
            'state' => 'unknown', // locked|unlocked|unknown
            'last_updated' => now()->toIso8601String(),
            'confidence' => 'low', // low|medium|high - based on how recent the state update is
        ];
    }

    /**
     * Build a compact status for a specific lock
     *
     * @return array<string,mixed>
     */
    public function buildLockStatus(Lock $lock): array
    {
        return [
            'id' => (string) $lock->id,
            'name' => $lock->name,
            'identifier' => $lock->identifier,
            'area' => [
                'id' => (string) $lock->area->id,
                'name' => $lock->area->name,
                'identifier' => $lock->area->identifier,
            ],
            'state' => $this->resolveLockStateForStatus($lock),
            'autolock' => [
                'enabled' => (bool) data_get($lock->config, 'locking.autolock_override_enabled'),
                'duration_seconds' => (int) data_get($lock->config, 'locking.autolock_override_duration', 0),
            ],
            'adapter_bindings_count' => $lock->adapterBindings()->count(),
        ];
    }

    /**
     * Build a compact status for a specific sensor
     *
     * @return array<string,mixed>
     */
    public function buildSensorStatus(Sensor $sensor): array
    {
        return [
            'id' => (string) $sensor->id,
            'name' => $sensor->name,
            'identifier' => $sensor->identifier,
            'area' => [
                'id' => (string) $sensor->area->id,
                'name' => $sensor->area->name,
                'identifier' => $sensor->area->identifier,
            ],
            'state' => (bool) $sensor->state ? 'on' : 'off',
            'state_raw' => (bool) $sensor->state,
            'updated_at' => $sensor->updated_at->toIso8601String(),
            'metadata' => $sensor->metadata ?? [],
        ];
    }
}
