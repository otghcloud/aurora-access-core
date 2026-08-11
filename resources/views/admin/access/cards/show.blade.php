@extends('layouts.admin')
@section('meta-page-title', 'Access Card')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Access Card</h1>
            <div class="text-muted">{{ $accessCard->description ?: ('Card #'.$accessCard->id) }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-cards.edit', $accessCard) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.access-cards.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">{{ $accessCard->description ?: '-' }}</dd>

                <dt class="col-sm-3">Card Number</dt>
                <dd class="col-sm-9">{{ $accessCard->card_number }}</dd>

                <dt class="col-sm-3">User</dt>
                <dd class="col-sm-9">{{ $accessCard->user?->name ?? '-' }}</dd>

                <dt class="col-sm-3">Active</dt>
                <dd class="col-sm-9">{{ $accessCard->active ? 'Yes' : 'No' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent Usage Events</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Granted</th>
                        <th>User</th>
                        <th>Originator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge text-bg-{{ $event->granted ? 'success' : 'secondary' }}">{{ $event->status_label }}</span></td>
                            <td>{{ $event->granted ? 'Yes' : 'No' }}</td>
                            <td>{{ $event->accessUser?->name ?? '-' }}</td>
                            <td>{{ $event->origin_label ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.access-events.show', $event) }}" class="btn btn-sm btn-outline-primary">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No usage events found for this card.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">
            {{ $events->links() }}
        </div>
    </div>
@endsection
