<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControlHealthService;

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
