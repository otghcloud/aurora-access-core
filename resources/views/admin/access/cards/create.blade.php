@extends('layouts.admin')
@section('meta-page-title', 'Create Access Card')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Access Card</h1>
        <a href="{{ $sourceEvent ? route('admin.access-events.show', $sourceEvent) : route('admin.access-cards.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if ($sourceEvent)
        <div class="alert alert-info">
            Creating card from event <strong>#{{ $sourceEvent->id }}</strong> captured at {{ $sourceEvent->created_at?->format('d/m/Y H:i:s') }}.
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-cards.store') }}">
                @csrf
                @if ($sourceEvent)
                    <input type="hidden" name="source_event_id" value="{{ $sourceEvent->id }}">
                @endif
                @include('admin.access.cards._form', ['accessCard' => null, 'accessUsers' => $accessUsers, 'initialCardNumber' => $initialCardNumber])
                <button type="submit" class="btn btn-primary mt-4">Create Card</button>
            </form>
        </div>
    </div>
@endsection
