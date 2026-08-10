@extends('layouts.admin-base')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Area Bindings Workspace</h1>
            <div class="text-muted">{{ $accessArea->name }} <code>{{ $accessArea->identifier }}</code></div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-areas.edit', $accessArea) }}" class="btn btn-outline-primary">Edit Area</a>
            <a href="{{ route('admin.access-areas.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Reader Binding Entrypoints</h2>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    @forelse ($accessArea->readers as $reader)
                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $reader->name }}</div>
                                <div class="small text-muted"><code>{{ $reader->identifier }}</code></div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.access-readers.bindings.edit', $reader) }}" class="btn btn-sm btn-outline-primary">Edit Reader Bindings</a>
                                <a href="{{ route('admin.access-bindings.index', ['target_type' => 'reader', 'target_id' => $reader->id]) }}" class="btn btn-sm btn-outline-secondary">View Filtered</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">No readers assigned to this area.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Lock Binding Entrypoints</h2>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    @forelse ($accessArea->locks as $lock)
                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $lock->name }}</div>
                                <div class="small text-muted"><code>{{ $lock->identifier }}</code></div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.access-locks.bindings.edit', $lock) }}" class="btn btn-sm btn-outline-dark">Edit Lock Bindings</a>
                                <a href="{{ route('admin.access-bindings.index', ['target_type' => 'lock', 'target_id' => $lock->id]) }}" class="btn btn-sm btn-outline-secondary">View Filtered</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">No locks assigned to this area.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Bindings In This Area</h2>
            <a href="{{ route('admin.access-bindings.index') }}" class="btn btn-sm btn-outline-secondary">Open Full Bindings Index</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Direction</th>
                        <th>Target</th>
                        <th>Action</th>
                        <th>Adapter</th>
                        <th>Channel</th>
                        <th>Source</th>
                        <th>Enabled</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bindings as $binding)
                        <tr>
                            <td>#{{ $binding->id }}</td>
                            <td><code>{{ $binding->direction }}</code></td>
                            <td><code>{{ $binding->target_type }}:{{ $binding->target_id }}</code></td>
                            <td>
                                @php($actionEnum = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($binding->action_key))
                                <code>{{ $actionEnum?->key() ?? $binding->action_key }}</code>
                            </td>
                            <td><code>{{ $binding->adapter_type }}</code></td>
                            <td><code>{{ $binding->channel ?? '-' }}</code></td>
                            <td>{{ $binding->source?->name ?? 'None' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $binding->enabled ? 'success' : 'secondary' }}">{{ $binding->enabled ? 'Yes' : 'No' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No bindings found for this area yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">
            {{ $bindings->links() }}
        </div>
    </div>
@endsection
