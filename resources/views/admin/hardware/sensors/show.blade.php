@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ $accessSensor->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-sensors.edit', $accessSensor) }}" class="btn btn-outline-primary">Edit</a>
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
@endsection
