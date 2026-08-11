@extends('layouts.admin-base')
@section('meta-page-title', 'Areas')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Areas</h1>
        <a href="{{ route('admin.access-areas.create') }}" class="btn btn-primary">New Area</a>
    </div>

    <div class="d-flex flex-column gap-4">
        @forelse ($accessAreas as $accessArea)
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="h4 mb-1">{{ $accessArea->name }}</h2>
                            <div class="small text-muted"><code>{{ $accessArea->identifier }}</code></div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge text-bg-primary">Readers: {{ $accessArea->readers_count }}</span>
                            <span class="badge text-bg-success">Locks: {{ $accessArea->locks_count }}</span>
                            <span class="badge text-bg-info">Switches: {{ $accessArea->switches_count }}</span>
                            <span class="badge text-bg-secondary">Permissions: {{ $accessArea->permissions_count }}</span>
                            <a href="{{ route('admin.access-areas.bindings', $accessArea) }}" class="btn btn-sm btn-outline-dark">Bindings</a>
                            <a href="{{ route('admin.access-areas.edit', $accessArea) }}" class="btn btn-sm btn-outline-primary">Edit Area</a>
                            <form method="POST" action="{{ route('admin.access-areas.destroy', $accessArea) }}" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this area?')">Delete</button>
                            </form>
                        </div>
                    </div>

                    @php
                        $control = $accessArea->control ?? [];
                        $lock = $control['lock'] ?? ['badge' => 'secondary', 'label' => 'Unknown', 'error' => null];
                        $autolockEnabled = (bool) ($control['autolock_enabled'] ?? false);
                        $autolockDuration = (int) ($control['autolock_duration'] ?? 0);
                    @endphp

                    <div class="border rounded p-3 bg-light d-flex flex-column gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">Area Controls</span>
                            <span class="badge text-bg-{{ $lock['badge'] ?? 'secondary' }}">Current Lock: {{ $lock['label'] ?? 'Unknown' }}</span>
                            <span class="badge text-bg-secondary">Default Auto-lock: {{ $autolockEnabled ? 'Enabled' : 'Disabled' }} ({{ $autolockDuration }}s)</span>
                            @if (! empty($lock['error']))
                                <span class="small text-muted">{{ $lock['error'] }}</span>
                            @endif
                        </div>

                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <form method="POST" action="{{ route('admin.access-areas.lock', $accessArea) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Lock Area</button>
                            </form>
                            <form method="POST" action="{{ route('admin.access-areas.unlock', $accessArea) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success">Unlock Area</button>
                            </form>
                            <form method="POST" action="{{ route('admin.access-areas.autolock', $accessArea) }}" class="d-flex align-items-end gap-2">
                                @csrf
                                <input type="hidden" name="autolock_enabled" value="{{ $autolockEnabled ? 0 : 1 }}">
                                <div>
                                    <label class="form-label form-label-sm mb-1">Auto-lock (seconds)</label>
                                    <input
                                        type="number"
                                        class="form-control form-control-sm"
                                        name="autolock_duration"
                                        min="0"
                                        value="{{ old('autolock_duration', $autolockDuration) }}"
                                    >
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $autolockEnabled ? 'Disable Auto-lock' : 'Enable Auto-lock' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 border">
                                <div class="card-header bg-light">Readers</div>
                                <div class="card-body d-flex flex-column gap-2">
                                    @forelse ($accessArea->readers as $reader)
                                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold">{{ $reader->name }}</div>
                                                <div class="small text-muted"><code>{{ $reader->identifier }}</code></div>
                                            </div>
                                            <a href="{{ route('admin.access-readers.show', $reader) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                                        </div>
                                    @empty
                                        <div class="small text-muted">No readers in this area.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card h-100 border">
                                <div class="card-header bg-light">Locks</div>
                                <div class="card-body d-flex flex-column gap-2">
                                    @forelse ($accessArea->locks as $lock)
                                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold">{{ $lock->name }}</div>
                                                <div class="small text-muted"><code>{{ $lock->identifier }}</code></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge text-bg-{{ $lock->is_primary ? 'success' : 'secondary' }}">{{ $lock->is_primary ? 'Primary' : 'Secondary' }}</span>
                                                <span class="badge text-bg-{{ $lock->usesAutolockOverride() ? 'warning' : 'secondary' }}">{{ $lock->usesAutolockOverride() ? 'Override' : 'Inherit' }}</span>
                                                <a href="{{ route('admin.access-locks.show', $lock) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="small text-muted">No locks in this area.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card h-100 border">
                                <div class="card-header bg-light">Switches</div>
                                <div class="card-body d-flex flex-column gap-2">
                                    @forelse ($accessArea->switches as $switch)
                                        <div class="border rounded p-2">
                                            <div class="fw-semibold">{{ $switch->name }}</div>
                                            <div class="small text-muted"><code>{{ $switch->identifier }}</code></div>
                                        </div>
                                    @empty
                                        <div class="small text-muted">No switches in this area.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5 text-muted">No areas configured yet.</div>
            </div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $accessAreas->links() }}
    </div>
@endsection
