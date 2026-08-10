@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Events</h1>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Status</th>
                        <th scope="col">Granted</th>
                        <th scope="col">User</th>
                        <th scope="col">Card</th>
                        <th scope="col">Originator</th>
                        <th scope="col">Area</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessEvents as $accessEvent)
                        @php
                            $area = $accessEvent->accessArea;
                            $originatorType = $accessEvent->origin_type;
                            $originatorLabel = $accessEvent->origin_label ?? '-';
                            $originatorRoute = null;

                            if ($originatorType === 'lock' && $accessEvent->accessLock) {
                                $originatorLabel = $accessEvent->accessLock->name ?: $accessEvent->origin_label ?: '-';
                                $originatorRoute = route('admin.access-locks.show', $accessEvent->accessLock);
                            } elseif ($originatorType === 'reader' && $accessEvent->originReader) {
                                $originatorLabel = $accessEvent->originReader->name ?: $accessEvent->origin_label ?: '-';
                                $originatorRoute = route('admin.access-readers.show', $accessEvent->originReader);
                            } elseif ($originatorType === 'area' && $accessEvent->accessArea) {
                                $originatorLabel = $accessEvent->accessArea->name ?: $accessEvent->origin_label ?: '-';
                                $originatorRoute = route('admin.access-areas.edit', $accessEvent->accessArea);
                            }
                        @endphp
                        <tr>
                            <td>{{ $accessEvent->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge text-bg-{{ $accessEvent->granted ? 'success' : 'secondary' }}">{{ $accessEvent->status_label }}</span></td>
                            <td>{{ $accessEvent->granted ? 'Yes' : 'No' }}</td>
                            <td>{{ $accessEvent->accessUser?->name ?? '-' }}</td>
                            <td>
                                @include('admin.components.card-display', [
                                    'accessCard' => $accessEvent->accessCard,
                                    'cardNumber' => $accessEvent->card_number,
                                ])
                            </td>
                            <td>
                                @if ($originatorRoute)
                                    <a href="{{ $originatorRoute }}">{{ $originatorLabel }}</a>
                                @else
                                    {{ $originatorLabel }}
                                @endif
                            </td>
                            <td>{{ $area?->name ?? '-' }}</td>
                            <td class="text-end">
                                @if (! $accessEvent->access_card_id && $accessEvent->card_number)
                                    <a href="{{ route('admin.access-cards.create', ['card_number' => $accessEvent->card_number, 'source_event_id' => $accessEvent->id]) }}" class="btn btn-sm btn-outline-success">
                                        Create Card
                                    </a>
                                @endif
                                <a href="{{ route('admin.access-events.show', $accessEvent) }}" class="btn btn-sm btn-outline-primary">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No events recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessEvents->links() }}
    </div>
@endsection
