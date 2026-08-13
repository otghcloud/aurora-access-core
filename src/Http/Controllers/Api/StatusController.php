<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Services\AccessControl\StatusAggregatorService;

class StatusController extends Controller
{
    public function __construct(private readonly StatusAggregatorService $statusAggregator) {}

    /**
     * Get hierarchical status of all areas, readers, locks, and sensors
     */
    public function index(): JsonResponse
    {
        $status = $this->statusAggregator->buildFullStatus();

        return response()->json($status);
    }
}
