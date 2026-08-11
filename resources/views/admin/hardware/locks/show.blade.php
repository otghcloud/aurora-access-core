@extends('layouts.admin')
@section('meta-page-title', 'Lock ' . $accessLock->name)

@section('content')
    @php
        $area = $accessLock->area;
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Lock {{ $accessLock->name }}</h1>
            <div class="text-muted">Identifier: {{ $accessLock->identifier }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-locks.bindings.edit', $accessLock) }}" class="btn btn-outline-dark">Bindings</a>
            <a href="{{ route('admin.access-locks.edit', $accessLock) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.access-locks.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Current Lock Status</h2>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <div>
                        <span class="badge text-bg-{{ $lockStatus['badge'] }} fs-6">{{ $lockStatus['label'] }}</span>
                    </div>
                    <div><strong>Channel:</strong> <code>{{ $lockStatus['channel'] ?? 'Not configured' }}</code></div>
                    <div><strong>Adapter:</strong> <code>{{ $lockStatus['adapter_type'] ?? '-' }}</code></div>
                    <div><strong>Signal Reversed:</strong> {{ ($lockStatus['signal_reversed'] ?? false) ? 'Yes' : 'No' }}</div>
                    @if (! empty($lockStatus['error']))
                        <div class="alert alert-warning mt-2 mb-0">
                            <div class="fw-semibold">Status query error</div>
                            <div class="small">{{ $lockStatus['error'] }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Area Control Context</h2>
                </div>
                <div class="card-body">
                    @php
                        $autolock = $autolockSettings ?? ['enabled' => false, 'duration' => 0, 'source' => 'area_default'];
                    @endphp
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h3 class="h6">Area</h3>
                            <div><strong>Name:</strong> {{ $area?->name ?? 'Unassigned' }}</div>
                            <div><strong>Identifier:</strong> <code>{{ $area?->identifier ?? '-' }}</code></div>
                            <div><strong>Primary Lock:</strong> {{ $accessLock->is_primary ? 'Yes' : 'No' }}</div>
                            <div><strong>Reader Count:</strong> {{ $area?->readers?->count() ?? 0 }}</div>
                            <div>
                                <strong>Auto-lock:</strong>
                                {{ ($autolock['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                                ({{ (int) ($autolock['duration'] ?? 0) }}s)
                                <span class="badge text-bg-{{ ($autolock['source'] ?? 'area_default') === 'lock_override' ? 'warning' : 'secondary' }}">
                                    {{ ($autolock['source'] ?? 'area_default') === 'lock_override' ? 'Lock Override' : 'Area Default' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6">MQTT</h3>
                            <div><strong>Area Slug:</strong> <code>{{ $area?->mqttAreaSlug() ?? '-' }}</code></div>
                            <div><strong>Command Topic:</strong> <code>{{ $area?->mqttCommandTopic() ?? '-' }}</code></div>
                            <div><strong>State Topic:</strong> <code>{{ $area?->mqttStateTopic() ?? '-' }}</code></div>
                            <div><strong>Events Topic:</strong> <code>{{ $area?->mqttEventsTopic() ?? '-' }}</code></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Lock Output Bindings</h2>
            <a href="{{ route('admin.access-locks.bindings.edit', $accessLock) }}" class="btn btn-sm btn-outline-dark">Edit Bindings</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Adapter</th>
                        <th>Channel</th>
                        <th>Reversed</th>
                        <th>Enabled</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lockBindings as $binding)
                        <tr>
                            @php($actionEnum = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($binding->action_key))
                            <td><code>{{ $actionEnum?->key() ?? $binding->action_key }}</code></td>
                            <td><code>{{ $binding->adapter_type }}</code></td>
                            <td><code>{{ $binding->channel ?? '-' }}</code></td>
                            <td>{{ $binding->signal_reversed ? 'Yes' : 'No' }}</td>
                            <td>{{ $binding->enabled ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No lock bindings configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Recent Area Reader Events</h2>
            <span class="text-muted small">Last {{ $recentEvents->count() }} events</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Reader</th>
                        <th>Reason</th>
                        <th>Event</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentEvents as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge text-bg-{{ $event->granted ? 'success' : 'secondary' }}">{{ $event->status_label }}</span></td>
                            <td><code>{{ $event->origin_label ?? '-' }}</code></td>
                            <td>{{ $event->reason ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.access-events.show', $event) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No events for this area yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
