<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Health;

use Illuminate\Http\Request;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\SerialReaderDiagnosticsServiceInterface;
use OTGH\AccessControl\Core\Services\AccessControlHealthService;

class HealthController extends Controller
{
    public function __invoke(Request $request, AccessControlHealthService $healthService, SerialReaderDiagnosticsServiceInterface $serialReaderDiagnosticsService): View
    {
        $probeReader = $request->string('reader')->toString();
        $requestedAutoRefresh = (int) $request->integer('auto_refresh', 0);
        $allowedAutoRefreshIntervals = [0, 30, 60, 300];
        $autoRefreshSeconds = in_array($requestedAutoRefresh, $allowedAutoRefreshIntervals, true)
            ? $requestedAutoRefresh
            : 0;
        $payload = $healthService->generate(null, $probeReader !== '' ? $probeReader : null);

        return view('admin.health.overview', [
            'health' => $payload,
            'serialDiagnostics' => $serialReaderDiagnosticsService->buildPayload(),
            'probeReader' => $probeReader,
            'autoRefreshSeconds' => $autoRefreshSeconds,
            'autoRefreshOptions' => $allowedAutoRefreshIntervals,
            'readers' => Reader::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }
}
