@extends('layouts.admin')
@section('meta-page-title', 'Sensor ' . $accessSensor->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ $accessSensor->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-sensors.edit', $accessSensor) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.access-bindings.index', ['target_type' => 'sensor', 'target_id' => $accessSensor->id]) }}" class="btn btn-outline-primary">Manage Input Bindings</a>
            <a href="{{ route('admin.access-sensors.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Identifier</div>
                    <div><code>{{ $accessSensor->identifier }}</code></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Area</div>
                    <div>{{ $accessSensor->area?->name ?? '-' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">State</div>
                    <div>
                        <span class="badge text-bg-{{ $accessSensor->state ? 'success' : 'secondary' }}">
                            {{ $accessSensor->state ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Config</div>
                    <pre class="mb-0 small">{{ json_encode($accessSensor->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Input Bindings</h2>
            <a href="{{ route('admin.access-bindings.index', ['target_type' => 'sensor', 'target_id' => $accessSensor->id]) }}" class="btn btn-sm btn-outline-primary">Manage</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Adapter</th>
                            <th>Action</th>
                            <th>Source</th>
                            <th>Channel</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sensorBindings as $binding)
                            @php
                                $action = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($binding->action_key);
                            @endphp
                            <tr>
                                <td><code>{{ $binding->adapter_type }}</code></td>
                                <td>{{ $action?->key() ?? $binding->action_key }}</td>
                                <td>{{ $binding->source?->name ?? ($binding->source?->identifier ?? '-') }}</td>
                                <td>{{ $binding->channel ?: '-' }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $binding->enabled ? 'success' : 'secondary' }}">
                                        {{ $binding->enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No input bindings configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
