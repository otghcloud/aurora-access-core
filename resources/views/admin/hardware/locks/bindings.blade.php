@extends('layouts.admin')
@section('meta-page-title', 'Lock Bindings')

@section('content')
    @php
        $rows = old('outputs', $bindingRows ?? []);
        $adapterOptions = $adapterTypeOptions ?? [];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Lock Bindings: {{ $accessLock->name }}</h1>
            <div class="text-muted">Configure lock-level output bindings owned by this lock.</div>
        </div>
        <a href="{{ route('admin.access-locks.show', $accessLock) }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-locks.bindings.update', $accessLock) }}">
                @csrf
                @method('PUT')

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Output Bindings</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-output-binding">Add Output</button>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-sm mb-0 align-middle" id="outputs-table">
                        <thead>
                            <tr>
                                <th>Enabled</th>
                                <th>Source</th>
                                <th>Adapter</th>
                                <th>Action</th>
                                <th>Channel/Tag</th>
                                <th>Periodic</th>
                                <th>Every (s)</th>
                                <th>Signal Reversed</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr>
                                    <td>
                                        <input type="hidden" name="outputs[{{ $index }}][enabled]" value="0">
                                        <input class="form-check-input" type="checkbox" name="outputs[{{ $index }}][enabled]" value="1" @checked((bool) ($row['enabled'] ?? true))>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="outputs[{{ $index }}][source_id]">
                                            <option value="">None</option>
                                            @foreach ($accessSources as $source)
                                                <option value="{{ $source->id }}" @selected((string) ($row['source_id'] ?? '') === (string) $source->id)>
                                                    {{ $source->name }} ({{ strtoupper($source->type) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="outputs[{{ $index }}][adapter_type]">
                                            <option value=""></option>
                                            @foreach ($adapterOptions as $adapter)
                                                <option value="{{ $adapter['value'] }}" @selected((string) ($row['adapter_type'] ?? '') === $adapter['value'])>{{ $adapter['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @php
                                            $selectedOutputAction = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($row['action_key'] ?? null)?->value;
                                        @endphp
                                        <select class="form-select form-select-sm" name="outputs[{{ $index }}][action_key]">
                                            <option value=""></option>
                                            @foreach (($outputActionOptions ?? []) as $option)
                                                <option value="{{ $option['value'] }}" @selected($selectedOutputAction === $option['value'])>{{ $option['key'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="outputs[{{ $index }}][channel]" value="{{ $row['channel'] ?? '' }}">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="outputs[{{ $index }}][mqtt_periodic_updates_enabled]">
                                            <option value="inherit" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? 'inherit') === 'inherit')>Inherit</option>
                                            <option value="1" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? '') === '1')>Enabled</option>
                                            <option value="0" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? '') === '0')>Disabled</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="1" class="form-control form-control-sm" name="outputs[{{ $index }}][mqtt_periodic_update_frequency_seconds]" value="{{ $row['mqtt_periodic_update_frequency_seconds'] ?? '' }}" placeholder="e.g. 60">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="outputs[{{ $index }}][signal_reversed]">
                                            <option value="0" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '0')>No</option>
                                            <option value="1" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '1')>Yes</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="outputs[{{ $index }}][config_json]" value="{{ $row['config_json'] ?? '{}' }}">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-binding-row">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Lock Bindings</button>
                </div>
            </form>
        </div>
    </div>

    <template id="output-binding-template">
        <tr>
            <td>
                <input type="hidden" name="outputs[__INDEX__][enabled]" value="0">
                <input class="form-check-input" type="checkbox" name="outputs[__INDEX__][enabled]" value="1" checked>
            </td>
            <td>
                <select class="form-select form-select-sm" name="outputs[__INDEX__][source_id]">
                    <option value="">None</option>
                    @foreach ($accessSources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }} ({{ strtoupper($source->type) }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" name="outputs[__INDEX__][adapter_type]">
                    <option value=""></option>
                    @foreach ($adapterOptions as $adapter)
                        <option value="{{ $adapter['value'] }}">{{ $adapter['label'] }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" name="outputs[__INDEX__][action_key]">
                    <option value=""></option>
                    @foreach (($outputActionOptions ?? []) as $option)
                        <option value="{{ $option['value'] }}">{{ $option['key'] }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm" name="outputs[__INDEX__][channel]"></td>
            <td>
                <select class="form-select form-select-sm" name="outputs[__INDEX__][mqtt_periodic_updates_enabled]">
                    <option value="inherit" selected>Inherit</option>
                    <option value="1">Enabled</option>
                    <option value="0">Disabled</option>
                </select>
            </td>
            <td><input type="number" min="1" class="form-control form-control-sm" name="outputs[__INDEX__][mqtt_periodic_update_frequency_seconds]" placeholder="e.g. 60"></td>
            <td>
                <select class="form-select form-select-sm" name="outputs[__INDEX__][signal_reversed]">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </td>
            <td>
                <input type="hidden" name="outputs[__INDEX__][config_json]" value="{}">
                <button type="button" class="btn btn-sm btn-outline-danger remove-binding-row">Remove</button>
            </td>
        </tr>
    </template>

    <script>
        (() => {
            const tableId = 'outputs-table';
            let nextIndex;

            const computeInitialIndex = () => {
                const tableBody = document.querySelector(`#${tableId} tbody`);
                if (!tableBody) {
                    return 0;
                }

                let maxIndex = -1;
                tableBody.querySelectorAll('[name^="outputs["]').forEach((element) => {
                    const name = element.getAttribute('name') || '';
                    const match = name.match(/^outputs\[(\d+)\]/);
                    if (match) {
                        maxIndex = Math.max(maxIndex, Number(match[1]));
                    }
                });

                return maxIndex + 1;
            };

            const addRow = () => {
                const tableBody = document.querySelector(`#${tableId} tbody`);
                const template = document.querySelector('#output-binding-template');
                if (!tableBody || !template) {
                    return;
                }

                if (typeof nextIndex === 'undefined') {
                    nextIndex = computeInitialIndex();
                }

                const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                nextIndex += 1;
                tableBody.insertAdjacentHTML('beforeend', html);
            };

            document.addEventListener('click', (event) => {
                if (event.target instanceof HTMLElement && event.target.id === 'add-output-binding') {
                    addRow();
                }

                if (event.target instanceof HTMLElement && event.target.classList.contains('remove-binding-row')) {
                    const row = event.target.closest('tr');
                    if (row) {
                        row.remove();
                    }
                }
            });
        })();
    </script>
@endsection
