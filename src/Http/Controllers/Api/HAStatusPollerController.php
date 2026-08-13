<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Light;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Services\HomeAssistant\HAIntegrationService;

class HAStatusPollerController
{
    protected HAIntegrationService $haIntegration;

    public function __construct(HAIntegrationService $haIntegration)
    {
        $this->haIntegration = $haIntegration;
    }

    /**
     * Get status for Home Assistant polling
     *
     * GET /api/ha/status
     *
     * Returns current state of all areas and devices in a format optimized
     * for Home Assistant polling integration. This endpoint is called
     * periodically by the HA custom integration to update device states.
     */
    public function getStatus(Request $request): JsonResponse
    {
        $areas = Area::with(['locks', 'sensors', 'lights'])
            ->orderBy('name')
            ->get();

        // Filter to only areas user has access to
        $userAreas = $areas->filter(fn (Area $area) => $request->user()->hasAreaPermission($area->id));

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'areas' => $userAreas->map(fn (Area $area) => $this->haIntegration->buildAreaStatusForHA($area))->values(),
            'device_count' => $userAreas->sum(fn (Area $area) => $area->locks->count() + $area->sensors->count() + ($area->lights()->count() ?? 0)
            ),
        ]);
    }

    /**
     * Get status for a specific area
     *
     * GET /api/ha/status/{areaId}
     *
     * Returns status for a single area, useful for HA discovery.
     */
    public function getAreaStatus(Request $request, Area $area): JsonResponse
    {
        if (! $request->user()->hasAreaPermission($area->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'area' => $this->haIntegration->buildAreaStatusForHA($area),
        ]);
    }

    /**
     * Get MQTT discovery manifests for all devices
     *
     * GET /api/ha/discovery
     *
     * Returns MQTT discovery payloads for all devices. These can be published
     * to Home Assistant's MQTT broker for auto-discovery.
     */
    public function getDiscoveryManifests(Request $request): JsonResponse
    {
        $manifests = [];

        // Get all areas user has access to
        $areas = Area::with(['locks', 'sensors', 'lights'])
            ->get()
            ->filter(fn (Area $area) => $request->user()->hasAreaPermission($area->id));

        foreach ($areas as $area) {
            // Add lock discovery manifests
            foreach ($area->locks as $lock) {
                $entity = $this->haIntegration->buildHAEntityLock($lock);
                $manifests[] = $this->haIntegration->buildMQTTDiscoveryPayload('lock', $entity);
            }

            // Add sensor discovery manifests
            foreach ($area->sensors as $sensor) {
                $entity = $this->haIntegration->buildHAEntityBinarySensor($sensor);
                $manifests[] = $this->haIntegration->buildMQTTDiscoveryPayload('binary_sensor', $entity);
            }

            // Add light discovery manifests
            foreach ($area->lights() as $light) {
                $entity = $this->haIntegration->buildHAEntityLight($light);
                $manifests[] = $this->haIntegration->buildMQTTDiscoveryPayload('light', $entity);
            }
        }

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'manifests' => $manifests,
            'count' => count($manifests),
        ]);
    }

    /**
     * Get discovery manifest for a specific lock
     *
     * GET /api/ha/discovery/locks/{lockId}
     */
    public function getLockDiscovery(Request $request, Lock $lock): JsonResponse
    {
        if (! $request->user()->hasAreaPermission($lock->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $entity = $this->haIntegration->buildHAEntityLock($lock);
        $manifest = $this->haIntegration->buildMQTTDiscoveryPayload('lock', $entity);

        return response()->json($manifest);
    }

    /**
     * Get discovery manifest for a specific sensor
     *
     * GET /api/ha/discovery/sensors/{sensorId}
     */
    public function getSensorDiscovery(Request $request, Sensor $sensor): JsonResponse
    {
        if (! $request->user()->hasAreaPermission($sensor->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $entity = $this->haIntegration->buildHAEntityBinarySensor($sensor);
        $manifest = $this->haIntegration->buildMQTTDiscoveryPayload('binary_sensor', $entity);

        return response()->json($manifest);
    }

    /**
     * Get discovery manifest for a specific light
     *
     * GET /api/ha/discovery/lights/{lightId}
     */
    public function getLightDiscovery(Request $request, Light $light): JsonResponse
    {
        if (! $request->user()->hasAreaPermission($light->area_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $entity = $this->haIntegration->buildHAEntityLight($light);
        $manifest = $this->haIntegration->buildMQTTDiscoveryPayload('light', $entity);

        return response()->json($manifest);
    }
}
