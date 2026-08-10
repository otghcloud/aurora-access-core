<?php

namespace App\Http\Controllers\Admin\Health;

use App\Http\Controllers\Controller;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\SerialReaderDiagnosticsServiceInterface;
use App\Services\AccessControlHealthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
