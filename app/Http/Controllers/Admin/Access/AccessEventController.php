<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\Event;
use App\Models\Access\Individual;
use Illuminate\View\View;

class AccessEventController extends Controller
{
    public function index(): View
    {
        return view('admin.access.events.index', [
            'accessEvents' => Event::with(['accessUser', 'accessCard', 'originReader', 'accessArea', 'accessLock'])
                ->latest('id')
                ->paginate(30),
        ]);
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
