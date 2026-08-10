<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessBindingResolver;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\AccessControl\LockStatusResolver;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;

class ReaderControlController extends Controller
{
    public function index(): JsonResponse
    {
        $readers = Reader::query()
            ->orderBy('name')
            ->get()
            ->map(function (Reader $reader): array {
                $lockBinding = app(AccessBindingResolver::class)->resolveLockPowerBinding($reader);

                return [
                    'id' => $reader->id,
                    'name' => $reader->name,
                    'identifier' => $reader->identifier,
                    'autolock_enabled' => (bool) (app(AutolockSettingsResolver::class)->resolveForReader($reader)['enabled'] ?? false),
                    'autolock_duration' => (int) (app(AutolockSettingsResolver::class)->resolveForReader($reader)['duration'] ?? 0),
                    'mqtt_command_topic' => $reader->mqttCommandTopic(),
                    'mqtt_state_topic' => $reader->mqttStateTopic(),
                    'lock_output' => [
                        'adapter_type' => $lockBinding?->adapterType,
                        'channel' => $lockBinding?->channel,
                        'signal_reversed' => $lockBinding?->signalReversed,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $readers,
        ]);
    }

    public function show(Reader $accessReader): JsonResponse
    {
        $status = $this->resolveLockStatus($accessReader);

        return response()->json([
            'data' => [
                'id' => $accessReader->id,
                'name' => $accessReader->name,
                'identifier' => $accessReader->identifier,
                'config' => $accessReader->config,
                'metadata' => $accessReader->metadata,
                'lock_status' => $status,
            ],
        ]);
    }

    public function lock(Request $request, Reader $accessReader): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $accessReader->area_id,
            'access_lock_id' => $accessReader->area?->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'lock',
            'origin_id' => $accessReader->area?->primaryLock()?->id ?? $accessReader->id,
            'origin_label' => $accessReader->area?->primaryLock()?->name ?? $accessReader->name,
            'granted' => true,
            'status' => 'api_lock_requested',
            'reason' => $validated['reason'] ?? 'Lock requested via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'lock_requested',
            ],
            'ip_address' => $request->ip(),
        ]);

        ProcessReaderEvent::dispatch(null, $accessReader, 1, false, 'api');

        return response()->json([
            'message' => 'Lock command queued.',
            'reader' => $accessReader->identifier,
            'target_value' => 1,
            'reason' => $validated['reason'] ?? null,
        ], 202);
    }

    public function unlock(Request $request, Reader $accessReader): JsonResponse
    {
        $validated = $request->validate([
            'allow_auto_relock' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $allowAutoRelock = (bool) ($validated['allow_auto_relock'] ?? true);

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $accessReader->area_id,
            'access_lock_id' => $accessReader->area?->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'lock',
            'origin_id' => $accessReader->area?->primaryLock()?->id ?? $accessReader->id,
            'origin_label' => $accessReader->area?->primaryLock()?->name ?? $accessReader->name,
            'granted' => true,
            'status' => 'api_unlock_requested',
            'reason' => $validated['reason'] ?? 'Unlock requested via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'unlock_requested',
                'allow_auto_relock' => $allowAutoRelock,
            ],
            'ip_address' => $request->ip(),
        ]);

        ProcessReaderEvent::dispatch(null, $accessReader, 0, $allowAutoRelock, 'api');

        return response()->json([
            'message' => 'Unlock command queued.',
            'reader' => $accessReader->identifier,
            'target_value' => 0,
            'allow_auto_relock' => $allowAutoRelock,
            'reason' => $validated['reason'] ?? null,
        ], 202);
    }

    public function setAutolock(Request $request, Reader $accessReader): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'sync_device_tag' => ['sometimes', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $duration = array_key_exists('duration', $validated)
            ? max(0, (int) $validated['duration'])
            : (int) data_get($accessReader->area?->config, 'locking.autolock_duration', 0);

        if (! $accessReader->area) {
            return response()->json([
                'message' => 'Reader must be assigned to an area before changing auto-lock settings.',
            ], 422);
        }

        $areaConfig = is_array($accessReader->area->config) ? $accessReader->area->config : [];
        data_set($areaConfig, 'locking.autolock_enabled', $enabled);
        data_set($areaConfig, 'locking.autolock_duration', $duration);
        $accessReader->area->config = $areaConfig;
        $accessReader->area->save();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $accessReader->area_id,
            'access_lock_id' => $accessReader->area?->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'area',
            'origin_id' => $accessReader->area_id,
            'origin_label' => $accessReader->area?->name,
            'granted' => true,
            'status' => 'api_autolock_updated',
            'reason' => $enabled ? 'Auto-lock enabled via API.' : 'Auto-lock disabled via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'autolock_updated',
                'autolock_enabled' => $enabled,
                'autolock_duration' => $duration,
            ],
            'ip_address' => $request->ip(),
        ]);

        app(AccessControlMqttPublisher::class)->publishReaderState($accessReader);

        return response()->json([
            'message' => 'Autolock settings updated.',
            'reader' => $accessReader->identifier,
            'autolock_enabled' => $enabled,
            'autolock_duration' => $duration,
        ]);
    }

    private function resolveLockStatus(Reader $reader): array
    {
        return app(LockStatusResolver::class)->resolve($reader);
    }
}
