<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Services\AccessControl\StatusAggregatorService;

class SensorStateController extends Controller
{
    public function __construct(private readonly StatusAggregatorService $statusAggregator) {}

    /**
     * List sensors, optionally filtered by area
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
        ]);

        $query = Sensor::query();

        if ($validated['area_id'] ?? null) {
            $query->where('area_id', $validated['area_id']);
        }

        $sensors = $query
            ->orderBy('area_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Sensor $sensor): array => $this->statusAggregator->buildSensorStatus($sensor))
            ->values();

        return response()->json([
            'data' => $sensors,
            'count' => $sensors->count(),
        ]);
    }

    /**
     * Get specific sensor state
     */
    public function show(Sensor $sensor): JsonResponse
    {
        $status = $this->statusAggregator->buildSensorStatus($sensor);

        return response()->json(['data' => $status]);
    }
}
