@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Bindings</h1>
        <a href="{{ route('admin.access-bindings.create') }}" class="btn btn-primary">New Binding</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.access-bindings.index') }}" class="row g-2">
                <div class="col-md-2">
                    <select class="form-select" name="direction">
                        <option value="">All Directions</option>
                        <option value="input" @selected(request('direction') === 'input')>Input</option>
                        <option value="output" @selected(request('direction') === 'output')>Output</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="adapter_type">
                        <option value="">All Adapters</option>
                        @foreach (($adapterTypeOptions ?? []) as $adapter)
                            <option value="{{ $adapter['value'] }}" @selected(request('adapter_type') === $adapter['value'])>{{ $adapter['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="target_type">
                        <option value="">All Targets</option>
                        <option value="reader" @selected(request('target_type') === 'reader')>Reader</option>
                        <option value="lock" @selected(request('target_type') === 'lock')>Lock</option>
                        <option value="area" @selected(request('target_type') === 'area')>Area</option>
                        <option value="switch" @selected(request('target_type') === 'switch')>Switch</option>
                        <option value="sensor" @selected(request('target_type') === 'sensor')>Sensor</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="action_key">
                        <option value="">All Actions</option>
                        @foreach (($actionOptions ?? []) as $option)
                            <option value="{{ $option['value'] }}" @selected((int) request('action_key', (string) ($selectedActionKey ?? '')) === (int) $option['value'])>{{ $option['key'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="source_id">
                        <option value="">All Sources</option>
                        @foreach ($accessSources as $source)
                            <option value="{{ $source->id }}" @selected((string) request('source_id') === (string) $source->id)>{{ $source->name }} ({{ strtoupper($source->type) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <select class="form-select" name="enabled">
                        <option value="">Any Status</option>
                        <option value="1" @selected(request('enabled') === '1')>Enabled</option>
                        <option value="0" @selected(request('enabled') === '0')>Disabled</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Direction</th>
                        <th>Adapter</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Source</th>
                        <th>Channel</th>
                        <th>Reversed</th>
                        <th>Enabled</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bindings as $binding)
                        <tr>
                            <td><span class="badge text-bg-{{ $binding->direction === 'input' ? 'info' : 'primary' }}">{{ strtoupper($binding->direction) }}</span></td>
                            <td><code>{{ $binding->adapter_type }}</code></td>
                            @php
                                $actionEnum = \App\Enums\AccessControl\AccessBindingActionKey::fromStored($binding->action_key);
                            @endphp
                            <td>
                                <code>{{ $actionEnum?->key() ?? $binding->action_key }}</code>
                                @if ($actionEnum)
                                    <div class="small text-muted">{{ $actionEnum->label() }}</div>
                                @endif
                            </td>
                            @php
                                $targetKey = $binding->target_type.':'.$binding->target_id;
                                $targetLabel = $targetLabels[$targetKey] ?? null;
                            @endphp
                            <td>
                                @if ($targetLabel)
                                    {{ $targetLabel }}
                                    <div class="small text-muted"><code>{{ $binding->target_type }}#{{ $binding->target_id }}</code></div>
                                @else
                                    <code>{{ $binding->target_type }}#{{ $binding->target_id }}</code>
                                @endif
                            </td>
                            <td>{{ $binding->source?->name ?? '-' }}</td>
                            <td><code>{{ $binding->channel ?? '-' }}</code></td>
                            <td>{{ $binding->signal_reversed ? 'Yes' : 'No' }}</td>
                            <td>{{ $binding->enabled ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-bindings.edit', $binding) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-bindings.destroy', $binding) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this binding?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No bindings configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $bindings->links() }}
    </div>
@endsection
