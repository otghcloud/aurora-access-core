<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Access\Area;
use App\Models\Access\Card;
use App\Models\Access\Event;
use App\Models\Access\Individual;
use App\Models\Hardware\Reader;
use App\Models\Hardware\Source;
use App\Services\AccessControlHealthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AccessControlHealthService $healthService): View
    {
        $health = $healthService->getLastHealthStatus();

        if ($health === null) {
            $health = $healthService->generate();
        }

        $healthIssues = array_values(array_filter(
            $health['checks'] ?? [],
            fn (array $check): bool => ($check['status'] ?? 'PASS') !== 'PASS'
        ));

        return view('admin.dashboard', [
            'cardCount' => Card::count(),
            'userCount' => Individual::count(),
            'roomCount' => Area::count(),
            'readerCount' => Reader::count(),
            'sourceCount' => Source::count(),
            /*            'opcSourceCount' => Source::query()->where('enabled', true)->whereIn('type', ['opc', 'opcua', 'opc_ua'])->count(),
            'opcMonitorRunningCount' => Source::query()
                ->where('enabled', true)
                ->whereIn('type', ['opc', 'opcua', 'opc_ua'])
                ->get()
                ->filter(fn (Source $source): bool => Cache::has($this->opcHeartbeatCacheKey((int) $source->id)))
                ->count(),
*/
            'eventCount' => Event::count(),
            'health' => $health,
            'healthIssues' => $healthIssues,
            'recentEvents' => Event::with(['accessUser', 'accessCard', 'originReader', 'accessArea', 'accessLock'])
                ->latest('id')
                ->take(10)
                ->get(),
        ]);
    }

    /*private function opcHeartbeatCacheKey(int $sourceId): string
    {
        return 'access_control:opc_monitor:source:'.$sourceId.':heartbeat';
    }*/
}
