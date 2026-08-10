@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Configuration</h1>
            <p class="text-muted mb-0">Centralized settings stored in the database with package-registered fields.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.system.configuration.update') }}">
        @csrf

        <div class="vstack gap-3">
            @forelse ($sections as $section)
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">{{ $section['section_label'] }}</h2>
                        @if (! empty($section['package']))
                            <span class="badge text-bg-secondary">{{ $section['package'] }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($section['fields'] as $field)
                                <div class="col-12">
                                    <label class="form-label" for="setting_{{ str_replace('.', '_', $field['key']) }}">{{ $field['label'] }}</label>

                                    @if ($field['type'] === 'boolean')
                                        <select
                                            class="form-select"
                                            id="setting_{{ str_replace('.', '_', $field['key']) }}"
                                            name="settings[{{ $field['key'] }}]"
                                        >
                                            <option value="1" @selected((bool) $field['value'] === true)>True</option>
                                            <option value="0" @selected((bool) $field['value'] === false)>False</option>
                                        </select>
                                    @elseif ($field['type'] === 'integer')
                                        <input
                                            class="form-control"
                                            id="setting_{{ str_replace('.', '_', $field['key']) }}"
                                            name="settings[{{ $field['key'] }}]"
                                            type="number"
                                            step="1"
                                            value="{{ old('settings.'.$field['key'], $field['value']) }}"
                                        >
                                    @elseif ($field['type'] === 'float')
                                        <input
                                            class="form-control"
                                            id="setting_{{ str_replace('.', '_', $field['key']) }}"
                                            name="settings[{{ $field['key'] }}]"
                                            type="number"
                                            step="any"
                                            value="{{ old('settings.'.$field['key'], $field['value']) }}"
                                        >
                                    @elseif ($field['type'] === 'json')
                                        <textarea
                                            class="form-control font-monospace"
                                            id="setting_{{ str_replace('.', '_', $field['key']) }}"
                                            name="settings[{{ $field['key'] }}]"
                                            rows="6"
                                        >{{ old('settings.'.$field['key'], json_encode($field['value'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                                    @else
                                        <input
                                            class="form-control"
                                            id="setting_{{ str_replace('.', '_', $field['key']) }}"
                                            name="settings[{{ $field['key'] }}]"
                                            type="text"
                                            value="{{ old('settings.'.$field['key'], is_scalar($field['value']) ? (string) $field['value'] : '') }}"
                                        >
                                    @endif

                                    <div class="form-text">
                                        <code>{{ $field['key'] }}</code>
                                        @if (! empty($field['description']))
                                            | {{ $field['description'] }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-secondary mb-0">
                    No configuration fields have been registered yet.
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </div>
    </form>
@endsection
