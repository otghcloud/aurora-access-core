<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Services\AccessControl\StatusAggregatorService;

class AreaStatusController extends Controller
{
    public function __construct(private readonly StatusAggregatorService $statusAggregator) {}

    /**
     * Get status for a specific area (all hardware)
     */
    public function show(Request $request, Area $area): JsonResponse
    {
        $this->authorizeAreaAccess($request, $area->id);

        $area->loadMissing(['readers', 'locks', 'sensors']);
        $status = $this->statusAggregator->buildAreaStatus($area);

        return response()->json(['data' => $status]);
    }

    /**
     * Update autolock settings for the area (cascades to all locks)
     */
    public function updateAutolock(Request $request, Area $area): JsonResponse
    {
        $this->authorizeAreaAccess($request, $area->id);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $config = is_array($area->config) ? $area->config : [];
        data_set($config, 'locking.autolock_enabled', $validated['enabled']);
        data_set($config, 'locking.autolock_duration', $validated['duration_seconds']);

        $area->update(['config' => $config]);

        // Create events for the area change
        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area->id,
            'access_lock_id' => $area->primaryLock()?->id,
            'user_id' => $request->user()?->id,
            'card_number' => null,
            'origin_type' => 'api',
            'origin_id' => null,
            'origin_label' => 'API Client',
            'granted' => true,
            'status' => 'api_autolock_updated',
            'reason' => 'Area autolock settings updated via API.',
            'metadata' => [
                'source' => 'api',
                'event' => 'autolock_updated',
                'area_id' => $area->id,
                'autolock_enabled' => $validated['enabled'],
                'autolock_duration' => $validated['duration_seconds'],
                'autolock_scope' => 'area_default',
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Area autolock settings updated.',
            'area' => $area->identifier,
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

        if (! $user->hasAreaPermission($areaId)) {
            abort(403, 'User does not have permission to access this area');
        }
    }
}
