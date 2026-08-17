@extends('layouts.admin-base')
@section('meta-page-title', 'Dashboard')
@section('page-title', 'System Dashboard')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Dashboard</h1>
    </div>

    @if (! empty($healthIssues))
        <div class="alert alert-{{ ($health['critical_failures'] ?? 0) > 0 ? 'danger' : 'warning' }} shadow-sm mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h5 mb-2">Health Attention Required</h2>
                    <p class="mb-2">
                        critical_failures={{ $health['critical_failures'] ?? 0 }}
                        warnings={{ $health['warnings'] ?? 0 }}
                        generated={{ \Illuminate\Support\Carbon::parse($health['generated_at'])->format('d/m/Y H:i:s') }}
                    </p>
                    <ul class="mb-0">
                        @foreach (array_slice($healthIssues, 0, 5) as $issue)
                            <li><strong>{{ $issue['name'] }}</strong>: {{ $issue['details'] }}</li>
                        @endforeach
                    </ul>
                </div>
                <a href="{{ route('admin.health') }}" class="btn btn-outline-dark btn-sm">Open Health</a>
            </div>
        </div>
    @elseif (! empty($health['generated_at']))
        <div class="alert alert-success shadow-sm mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong>Health OK.</strong>
                Latest check {{ \Illuminate\Support\Carbon::parse($health['generated_at'])->format('d/m/Y H:i:s') }}.
            </div>
            <a href="{{ route('admin.health') }}" class="btn btn-outline-success btn-sm">View Health Details</a>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Areas</p>
                    <p class="display-6 mb-0">{{ $roomCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Users</p>
                    <p class="display-6 mb-0">{{ $userCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Cards</p>
                    <p class="display-6 mb-0">{{ $cardCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Readers</p>
                    <p class="display-6 mb-0">{{ $readerCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Sources</p>
                    <p class="display-6 mb-0">{{ $sourceCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">Events</p>
                    <p class="display-6 mb-0">{{ number_format($eventCount) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Configured Locks</h2>
            <p class="text-muted mb-0">Live status and manual controls for each configured lock.</p>
        </div>
    </div>

    <livewire:admin.dashboard-lock-cards />

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent Access Events</h2>
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentEvents as $event)
                        @php
                            $area = $event->accessArea;
                            $originatorType = $event->origin_type;
                            $originatorLabel = $event->origin_label ?? '-';
                            $originatorRoute = null;

                            if ($originatorType === 'lock' && $event->accessLock) {
                                $originatorLabel = $event->accessLock->name ?: $event->origin_label ?: '-';
                                $originatorRoute = route('admin.access-locks.show', $event->accessLock);
                            } elseif ($originatorType === 'reader' && $event->originReader) {
                                $originatorLabel = $event->originReader->name ?: $event->origin_label ?: '-';
                                $originatorRoute = route('admin.access-readers.show', $event->originReader);
                            } elseif ($originatorType === 'area' && $event->accessArea) {
                                $originatorLabel = $event->accessArea->name ?: $event->origin_label ?: '-';
                                $originatorRoute = route('admin.access-areas.edit', $event->accessArea);
                            }
                        @endphp
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge text-bg-{{ $event->granted ? 'success' : 'secondary' }}">{{ $event->status_label }}</span></td>
                            <td>{{ $event->granted ? 'Yes' : 'No' }}</td>
                            <td>{{ $event->accessUser?->name ?? '-' }}</td>
                            <td>
                                @include('admin.components.card-display', [
                                    'accessCard' => $event->accessCard,
                                    'cardNumber' => $event->card_number,
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No events recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
