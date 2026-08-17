<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Access;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use OTGH\AccessControl\Core\DataTables\Admin\AccessCardsDataTable;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;

class AccessCardController extends Controller
{
    public function index(AccessCardsDataTable $dataTable)
    {
        return $dataTable->render('admin.access.cards.index');
    }

    public function create(Request $request): View
    {
        $eventId = $request->integer('source_event_id');
        $sourceEvent = $eventId ? Event::query()->find($eventId) : null;

        return view('admin.access.cards.create', [
            'accessUsers' => Individual::orderBy('name')->get(),
            'sourceEvent' => $sourceEvent,
            'initialCardNumber' => $request->string('card_number')->toString() ?: $sourceEvent?->card_number,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:individuals,id'],
            'card_number' => ['required', 'string', 'max:255', 'unique:cards,card_number'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
            'source_event_id' => ['nullable', 'integer', 'exists:events,id'],
        ]);

        $sourceEventId = $validated['source_event_id'] ?? null;
        unset($validated['source_event_id']);

        Card::create($validated);

        if ($sourceEventId) {
            return redirect()
                ->route('admin.access-events.show', $sourceEventId)
                ->with('status', 'Access card created and assigned successfully.');
        }

        return redirect()->route('admin.access-cards.index')->with('status', 'Access card created successfully.');
    }

    public function edit(Card $card): View
    {
        return view('admin.access.cards.edit', [
            'accessCard' => $card,
            'accessUsers' => Individual::orderBy('name')->get(),
        ]);
    }

    public function show(Card $card): View
    {
        $card->load('user');

        $events = Event::query()
            ->with(['accessUser', 'originReader', 'accessCard'])
            ->where(function ($query) use ($card): void {
                $query->where('access_card_id', $card->id)
                    ->orWhere('card_number', $card->card_number);
            })
            ->latest('id')
            ->paginate(30);

        return view('admin.access.cards.show', [
            'accessCard' => $card,
            'events' => $events,
        ]);
    }

    public function update(Request $request, Card $card): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:individuals,id'],
            'card_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cards', 'card_number')->ignore($card->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
        ]);

        $card->update($validated);

        return redirect()->route('admin.access-cards.index')->with('status', 'Access card updated successfully.');
    }

    public function destroy(Card $card): RedirectResponse
    {
        $card->delete();

        return redirect()->route('admin.access-cards.index')->with('status', 'Access card deleted successfully.');
    }
}
