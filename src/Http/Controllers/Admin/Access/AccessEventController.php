<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Access;

use Illuminate\View\View;
use OTGH\AccessControl\Core\DataTables\Admin\AccessEventsDataTable;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;

class AccessEventController extends Controller
{
    public function index(AccessEventsDataTable $dataTable)
    {
        return $dataTable->render('admin.access.events.index');
    }

    public function show(Event $event): View
    {
        $event->load(['accessUser', 'accessCard', 'originReader', 'accessArea', 'accessLock']);

        return view('admin.access.events.show', [
            'accessEvent' => $event,
            'accessUsers' => Individual::orderBy('name')->get(),
        ]);
    }
}
