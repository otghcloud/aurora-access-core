<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OTGH\AccessControl\Core\Jobs\ProcessLightEvent;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\HomeAssistant\HAIntegrationService;

class HAWebhookController
{
    protected HAIntegrationService $haIntegration;

    public function __construct(
        HAIntegrationService $haIntegration,
        private readonly AutolockSettingsResolver $autolockSettingsResolver,
    ) {
        $this->haIntegration = $haIntegration;
    }

    /**
     * Handle webhook commands from Home Assistant
     *
     * POST /api/ha/webhook
     *
     * Receives commands from Home Assistant when users control devices in HA.
     * Supports lock commands (lock/unlock), sensor queries, and light commands.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:lock_command,light_command,sensor_query,autolock_command',
            'device_id' => 'required|string',
            'action' => 'required|string',
            'area_id' => 'required|integer',
            'code' => 'nullable|string',
            'value' => 'nullable|string|max:255',
        ]);

        // Authorize area access
        if (! $request->user()->hasAreaPermission($validated['area_id'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            return match ($validated['type']) {
                'lock_command' => $this->handleLockCommand($request->user(), $validated),
                'autolock_command' => $this->handleAutolockCommand($request, $validated),
                'light_command' => $this->handleLightCommand($request->user(), $validated),
                'sensor_query' => $this->handleSensorQuery($request->user(), $validated),
                default => response()->json(['error' => 'Unknown command type'], 400),
            };
        } catch (\Exception $e) {
            \Log::error('HA Webhook Error: '.$e->getMessage(), [
                'device_id' => $validated['device_id'],
                'type' => $validated['type'],
            ]);

            return response()->json(['error' => 'Command failed'], 500);
        }
    }

    /**
     * Handle lock commands from Home Assistant
     *
     * @param  mixed  $user
     */
    protected function handleLockCommand($user, array $data): JsonResponse
    {
        // Extract lock ID from device_id (e.g., "aurora_lock_123" -> 123)
        preg_match('/lock_(\d+)/', $data['device_id'], $matches);
        $lockId = $matches[1] ?? null;

        if (! $lockId) {
            return response()->json(['error' => 'Invalid device ID'], 400);
        }

        $lock = Lock::findOrFail($lockId);

        // Verify user has access to lock's area
        if (! $user->hasAreaPermission($lock->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return match ($data['action']) {
            'lock' => $this->lockDoor($user, $lock, 'Home Assistant command'),
            'unlock' => $this->unlockDoor($user, $lock, 'Home Assistant command'),
            default => response()->json(['error' => 'Unknown lock action'], 400),
        };
    }

    protected function handleAutolockCommand(Request $request, array $data): JsonResponse
    {
        preg_match('/^aurora_lock_(\d+)$/', $data['device_id'], $matches);
        $lockId = $matches[1] ?? null;

        if (! $lockId) {
            return response()->json(['error' => 'Invalid device ID'], 400);
        }

        $lock = Lock::query()->with('area')->findOrFail($lockId);
        if ((int) $lock->area_id !== (int) $data['area_id']) {
            return response()->json(['error' => 'Lock does not belong to the requested area'], 422);
        }

        if (! $request->user()->hasAreaPermission($lock->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $current = $this->autolockSettingsResolver->resolveForAreaAndLock($lock->area, $lock);
        $enabled = $current['enabled'];
        $duration = min(3600, max(0, $current['duration']));

        if ($data['action'] === 'set_enabled') {
            $value = strtolower((string) ($data['value'] ?? ''));
            if (! in_array($value, ['0', '1', 'false', 'true'], true)) {
                return response()->json(['message' => 'The auto-lock enabled value must be boolean.'], 422);
            }
            $enabled = in_array($value, ['1', 'true'], true);
        } elseif ($data['action'] === 'set_duration') {
            $value = (string) ($data['value'] ?? '');
            if (! preg_match('/^\d+$/', $value) || (int) $value > 3600) {
                return response()->json(['message' => 'The auto-lock duration must be an integer between 0 and 3600 seconds.'], 422);
            }
            $duration = (int) $value;
        } else {
            return response()->json(['error' => 'Unknown auto-lock action'], 400);
        }

        $config = is_array($lock->config) ? $lock->config : [];
        data_set($config, 'locking.autolock_override_enabled', $enabled);
        data_set($config, 'locking.autolock_override_duration', $duration);

        DB::transaction(function () use ($lock, $config, $request, $data, $enabled, $duration): void {
            $lock->update(['config' => $config]);

            Event::create([
                'access_card_id' => null,
                'access_area_id' => $lock->area_id,
                'access_lock_id' => $lock->id,
                'user_id' => $request->user()?->id,
                'card_number' => null,
                'origin_type' => 'ha_webhook',
                'origin_id' => null,
                'origin_label' => 'Home Assistant',
                'granted' => true,
                'status' => 'ha_autolock_updated',
                'reason' => 'Auto-lock settings updated via Home Assistant.',
                'metadata' => [
                    'source' => 'home_assistant',
                    'event' => 'autolock_updated',
                    'action' => $data['action'],
                    'lock_id' => $lock->id,
                    'autolock_enabled' => $enabled,
                    'autolock_duration' => $duration,
                    'autolock_scope' => 'lock_override',
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return response()->json([
            'success' => true,
            'lock' => $lock->identifier,
            'action' => $data['action'],
            'autolock' => [
                'enabled' => $enabled,
                'duration_seconds' => $duration,
                'source' => 'lock_override',
            ],
        ]);
    }

    /**
     * Handle light commands from Home Assistant
     *
     * @param  mixed  $user
     */
    protected function handleLightCommand($user, array $data): JsonResponse
    {
        // Extract light ID from device_id (e.g., "aurora_light_456" -> 456)
        preg_match('/light_(\d+)/', $data['device_id'], $matches);
        $lightId = $matches[1] ?? null;

        if (! $lightId) {
            return response()->json(['error' => 'Invalid device ID'], 400);
        }

        $light = Light::findOrFail($lightId);

        // Verify user has access to light's area
        if (! $user->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return match ($data['action']) {
            'on' => $this->turnOnLight($user, $light, $data['value'] ?? null),
            'off' => $this->turnOffLight($user, $light),
            'brightness' => $this->setLightBrightness($user, $light, (int) $data['value']),
            'color' => $this->setLightColor($user, $light, $data['value']),
            default => response()->json(['error' => 'Unknown light action'], 400),
        };
    }

    /**
     * Handle sensor state queries from Home Assistant
     *
     * @param  mixed  $user
     */
    protected function handleSensorQuery($user, array $data): JsonResponse
    {
        // Extract sensor ID from device_id
        preg_match('/sensor_(\d+)/', $data['device_id'], $matches);
        $sensorId = $matches[1] ?? null;

        if (! $sensorId) {
            return response()->json(['error' => 'Invalid device ID'], 400);
        }

        $sensor = Sensor::findOrFail($sensorId);

        // Verify user has access to sensor's area
        if (! $user->hasAreaPermission($sensor->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'device_id' => $data['device_id'],
            'state' => $sensor->state ? 'on' : 'off',
            'state_raw' => $sensor->state,
            'last_updated' => $sensor->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Lock door
     *
     * @param  mixed  $user
     */
    protected function lockDoor($user, Lock $lock, string $reason): JsonResponse
    {
        Event::create([
            'access_lock_id' => $lock->id,
            'status' => 'api_lock_requested',
            'origin_type' => 'ha_webhook',
            'reason' => $reason,
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'lock_requested',
                'lock_id' => $lock->id,
                'lock_identifier' => $lock->identifier,
            ],
            'ip_address' => request()->ip(),
        ]);

        $reader = $this->readerForLock($lock);

        if ($reader === null) {
            return response()->json([
                'error' => 'No reader is configured for this lock area',
            ], 409);
        }

        ProcessReaderEvent::dispatch(null, $reader, 1, false, 'ha_webhook');

        return response()->json([
            'success' => true,
            'lock' => $lock->identifier,
            'action' => 'lock',
        ]);
    }

    /**
     * Unlock door
     *
     * @param  mixed  $user
     */
    protected function unlockDoor($user, Lock $lock, string $reason): JsonResponse
    {
        Event::create([
            'access_lock_id' => $lock->id,
            'status' => 'api_unlock_requested',
            'origin_type' => 'ha_webhook',
            'reason' => $reason,
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'unlock_requested',
                'lock_id' => $lock->id,
                'lock_identifier' => $lock->identifier,
            ],
            'ip_address' => request()->ip(),
        ]);

        $reader = $this->readerForLock($lock);

        if ($reader === null) {
            return response()->json([
                'error' => 'No reader is configured for this lock area',
            ], 409);
        }

        ProcessReaderEvent::dispatch(null, $reader, 0, true, 'ha_webhook');

        return response()->json([
            'success' => true,
            'lock' => $lock->identifier,
            'action' => 'unlock',
        ]);
    }

    private function readerForLock(Lock $lock): ?Reader
    {
        if (Schema::hasTable('reader_lock_bindings')) {
            $reader = ReaderLockBinding::query()
                ->where('lock_id', $lock->id)
                ->where('enabled', true)
                ->with('reader')
                ->orderBy('id')
                ->get()
                ->pluck('reader')
                ->filter()
                ->first();

            if ($reader !== null) {
                return $reader;
            }
        }

        return $lock->area?->readers()->orderBy('id')->first();
    }

    /**
     * Turn on light
     *
     * @param  mixed  $user
     * @param  Light  $light
     */
    protected function turnOnLight($user, $light, ?string $brightness = null): JsonResponse
    {
        $light->update([
            'state' => true,
            'brightness' => $brightness ? (int) $brightness : ($light->brightness ?? 100),
        ]);

        Event::create([
            'access_light_id' => $light->id,
            'status' => 'success',
            'origin_type' => 'ha_webhook',
            'reason' => 'Home Assistant command',
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'light_on_requested',
                'light_id' => $light->id,
                'brightness' => $brightness ? (int) $brightness : null,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'on',
            brightness: $brightness ? (int) $brightness : null,
            originType: 'ha_webhook',
        ));

        return response()->json([
            'success' => true,
            'light' => $light->identifier,
            'action' => 'on',
            'brightness' => $light->brightness,
        ]);
    }

    /**
     * Turn off light
     *
     * @param  mixed  $user
     * @param  Light  $light
     */
    protected function turnOffLight($user, $light): JsonResponse
    {
        $light->update(['state' => false]);

        Event::create([
            'access_light_id' => $light->id,
            'status' => 'success',
            'origin_type' => 'ha_webhook',
            'reason' => 'Home Assistant command',
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'light_off_requested',
                'light_id' => $light->id,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'off',
            originType: 'ha_webhook',
        ));

        return response()->json([
            'success' => true,
            'light' => $light->identifier,
            'action' => 'off',
        ]);
    }

    /**
     * Set light brightness
     *
     * @param  mixed  $user
     * @param  Light  $light
     */
    protected function setLightBrightness($user, $light, int $brightness): JsonResponse
    {
        $brightness = max(0, min(100, $brightness));
        $light->update(['brightness' => $brightness]);

        Event::create([
            'access_light_id' => $light->id,
            'status' => 'success',
            'origin_type' => 'ha_webhook',
            'reason' => 'Home Assistant command',
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'light_brightness_set',
                'light_id' => $light->id,
                'brightness' => $brightness,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'brightness',
            brightness: $brightness,
            originType: 'ha_webhook',
        ));

        return response()->json([
            'success' => true,
            'light' => $light->identifier,
            'brightness' => $brightness,
        ]);
    }

    /**
     * Set light color
     *
     * @param  mixed  $user
     * @param  Light  $light
     */
    protected function setLightColor($user, $light, string $color): JsonResponse
    {
        // Validate hex color
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return response()->json(['error' => 'Invalid color format'], 400);
        }

        $light->update(['color' => $color]);

        Event::create([
            'access_light_id' => $light->id,
            'status' => 'success',
            'origin_type' => 'ha_webhook',
            'reason' => 'Home Assistant command',
            'metadata' => [
                'source' => 'home_assistant',
                'event' => 'light_color_set',
                'light_id' => $light->id,
                'color' => $color,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Queue light command
        dispatch(new ProcessLightEvent(
            lightId: $light->id,
            action: 'color',
            color: $color,
            originType: 'ha_webhook',
        ));

        return response()->json([
            'success' => true,
            'light' => $light->identifier,
            'color' => $color,
        ]);
    }
}
