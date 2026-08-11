@extends('layouts.admin')
@section('meta-page-title', 'Access Event')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Event #{{ $accessEvent->id }}</h1>
        <a href="{{ route('admin.access-events.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $accessEvent->created_at?->format('d/m/Y H:i:s') }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    {{ $accessEvent->status_label }}
                    @if ($accessEvent->status)
                        <small class="text-muted d-block">{{ $accessEvent->status }}</small>
                    @endif
                </dd>

                <dt class="col-sm-3">Granted</dt>
                <dd class="col-sm-9">{{ $accessEvent->granted ? 'Yes' : 'No' }}</dd>

                <dt class="col-sm-3">User</dt>
                <dd class="col-sm-9">{{ $accessEvent->accessUser?->name ?? '-' }}</dd>

                <dt class="col-sm-3">Card</dt>
                <dd class="col-sm-9">
                    @include('admin.components.card-display', [
                        'accessCard' => $accessEvent->accessCard,
                        'cardNumber' => $accessEvent->card_number,
                    ])
                </dd>

                <dt class="col-sm-3">Reader Identifier</dt>
                <dd class="col-sm-9">{{ $accessEvent->origin_label ?? '-' }}</dd>

                <dt class="col-sm-3">Reason</dt>
                <dd class="col-sm-9">{{ $accessEvent->reason ?? '-' }}</dd>

                <dt class="col-sm-3">IP Address</dt>
                <dd class="col-sm-9">{{ $accessEvent->ip_address ?? '-' }}</dd>

                <dt class="col-sm-3">Metadata</dt>
                <dd class="col-sm-9">
                    @if ($accessEvent->metadata)
                        Available
                    @else
                        -
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    @if (! $accessEvent->access_card_id && $accessEvent->card_number)
        <div class="card shadow-sm mt-4" id="create-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Create Card From This Event</h2>
                <a href="{{ route('admin.access-cards.create', ['card_number' => $accessEvent->card_number, 'source_event_id' => $accessEvent->id]) }}" class="btn btn-sm btn-outline-secondary">
                    Open Full Form
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.access-cards.store') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="source_event_id" value="{{ $accessEvent->id }}">

                    <div class="col-md-6">
                        <label for="user_id" class="form-label">Assign To User</label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">Select a user</option>
                            @foreach ($accessUsers as $user)
                                <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="card_number" class="form-label">Card Number</label>
                        <input type="text" class="form-control" id="card_number" name="card_number" value="{{ old('card_number', $accessEvent->card_number) }}" required maxlength="255">
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description" value="{{ old('description', 'Created from event #'.$accessEvent->id) }}" maxlength="500">
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Status</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="active" id="active_yes" value="1" @checked((string) old('active', '1') === '1')>
                            <label class="form-check-label" for="active_yes">Active</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="active" id="active_no" value="0" @checked((string) old('active', '1') === '0')>
                            <label class="form-check-label" for="active_no">Inactive</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Create And Assign Card</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
