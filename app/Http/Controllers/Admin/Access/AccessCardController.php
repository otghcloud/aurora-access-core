<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\Card;
use App\Models\Access\Event;
use App\Models\Access\Individual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessCardController extends Controller
{
    public function index(): View
    {
        return view('admin.access.cards.index', [
            'accessCards' => Card::with('user')->latest('id')->paginate(20),
        ]);
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
