<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Services\AccessControl\StatusAggregatorService;

class LockControlController extends Controller
{
    public function __construct(private readonly StatusAggregatorService $statusAggregator) {}

    /**
     * Get specific lock status
     */
    public function show(Request $request, Lock $lock): JsonResponse
    {
        $this->authorizeAreaAccess($request, $lock->area_id);

        $status = $this->statusAggregator->buildLockStatus($lock);

        return response()->json(['data' => $status]);
    }

    /**
     * Lock the door
     */
    public function lock(Request $request, Lock $lock): JsonResponse
    {
        $this->authorizeAreaAccess($request, $lock->area_id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Find a reader in this area to dispatch the lock command
        $reader = $lock->area->readers()->first();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $lock->area_id,
            'access_lock_id' => $lock->id,
            'user_id' => $request->user()?->id,
            'card_number' => null,
            'origin_type' => 'api',
            'origin_id' => null,
            'origin_label' => 'API Client',
            'granted' => true,
            'status' => 'api_lock_requested',
            'reason' => $validated['reason'] ?? 'Lock requested via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'lock_requested',
                'lock_id' => $lock->id,
            ],
            'ip_address' => $request->ip(),
        ]);

        if ($reader) {
            ProcessReaderEvent::dispatch(null, $reader, 1, false, 'api');
        }

        return response()->json([
            'message' => 'Lock command queued.',
            'lock' => $lock->identifier,
            'status' => 'locked',
        ], 202);
    }

    /**
     * Unlock the door
     */
    public function unlock(Request $request, Lock $lock): JsonResponse
    {
        $this->authorizeAreaAccess($request, $lock->area_id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reader = $lock->area->readers()->first();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $lock->area_id,
            'access_lock_id' => $lock->id,
            'user_id' => $request->user()?->id,
            'card_number' => null,
            'origin_type' => 'api',
            'origin_id' => null,
            'origin_label' => 'API Client',
            'granted' => true,
            'status' => 'api_unlock_requested',
            'reason' => $validated['reason'] ?? 'Unlock requested via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'unlock_requested',
                'lock_id' => $lock->id,
            ],
            'ip_address' => $request->ip(),
        ]);

        if ($reader) {
            ProcessReaderEvent::dispatch(null, $reader, 0, false, 'api');
        }

        return response()->json([
            'message' => 'Unlock command queued.',
            'lock' => $lock->identifier,
            'status' => 'unlocked',
        ], 202);
    }

    /**
     * Toggle lock state
     */
    public function toggle(Request $request, Lock $lock): JsonResponse
    {
        $this->authorizeAreaAccess($request, $lock->area_id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reader = $lock->area->readers()->first();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $lock->area_id,
            'access_lock_id' => $lock->id,
            'user_id' => $request->user()?->id,
            'card_number' => null,
            'origin_type' => 'api',
            'origin_id' => null,
            'origin_label' => 'API Client',
            'granted' => true,
            'status' => 'api_toggle_requested',
            'reason' => $validated['reason'] ?? 'Toggle requested via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'toggle_requested',
                'lock_id' => $lock->id,
            ],
            'ip_address' => $request->ip(),
        ]);

        if ($reader) {
            // null = toggle (auto-determine next state)
            ProcessReaderEvent::dispatch(null, $reader, null, false, 'api');
        }

        return response()->json([
            'message' => 'Toggle command queued.',
            'lock' => $lock->identifier,
        ], 202);
    }

    /**
     * Update autolock settings for a lock
     */
    public function updateAutolock(Request $request, Lock $lock): JsonResponse
    {
        $this->authorizeAreaAccess($request, $lock->area_id);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $config = is_array($lock->config) ? $lock->config : [];
        data_set($config, 'locking.autolock_override_enabled', $validated['enabled']);
        data_set($config, 'locking.autolock_override_duration', $validated['duration_seconds']);

        $lock->update(['config' => $config]);

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $lock->area_id,
            'access_lock_id' => $lock->id,
            'user_id' => $request->user()?->id,
            'card_number' => null,
            'origin_type' => 'api',
            'origin_id' => null,
            'origin_label' => 'API Client',
            'granted' => true,
            'status' => 'api_autolock_updated',
            'reason' => 'Autolock settings updated via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'autolock_updated',
                'lock_id' => $lock->id,
                'autolock_enabled' => $validated['enabled'],
                'autolock_duration' => $validated['duration_seconds'],
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Autolock settings updated.',
            'lock' => $lock->identifier,
            'autolock' => [
                'enabled' => $validated['enabled'],
                'duration_seconds' => $validated['duration_seconds'],
            ],
        ]);
    }

    /**
     * Authorize that the user has access to this area
     */
    private function authorizeAreaAccess(Request $request, int $areaId): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Check if user has permission for this area
        $hasPermission = AreaPermission::query()
            ->where('individual_id', $user->id)
            ->where('area_id', $areaId)
            ->where('permission', 'allow')
            ->exists();

        if (! $hasPermission) {
            abort(403, 'User does not have permission to access this area');
        }
    }
}
