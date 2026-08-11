@php
    $inputRows = old('inputs', $inputBindings ?? []);
    $adapterOptions = $adapterTypeOptions ?? [];
    $actionOptions = $sensorInputActionOptions ?? [];
@endphp

<div class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Input Bindings</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-sensor-input-binding">Add Input</button>
    </div>
    <div class="table-responsive border rounded">
        <table class="table table-sm mb-0 align-middle" id="sensor-inputs-table">
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
                @foreach ($inputRows as $index => $row)
                    <tr>
                        <td>
                            <input type="hidden" name="inputs[{{ $index }}][enabled]" value="0">
                            <input class="form-check-input" type="checkbox" name="inputs[{{ $index }}][enabled]" value="1" @checked((bool) ($row['enabled'] ?? true))>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="inputs[{{ $index }}][source_id]">
                                <option value="">None</option>
                                @foreach ($accessSources as $source)
                                    <option value="{{ $source->id }}" @selected((string) ($row['source_id'] ?? '') === (string) $source->id)>{{ $source->name }} ({{ strtoupper($source->type) }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="inputs[{{ $index }}][adapter_type]">
                                <option value=""></option>
                                @foreach ($adapterOptions as $adapter)
                                    <option value="{{ $adapter['value'] }}" @selected((string) ($row['adapter_type'] ?? '') === $adapter['value'])>{{ $adapter['label'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @php
                                $selectedAction = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($row['action_key'] ?? null)?->value;
                            @endphp
                            <select class="form-select form-select-sm" name="inputs[{{ $index }}][action_key]">
                                <option value=""></option>
                                @foreach ($actionOptions as $option)
                                    <option value="{{ $option['value'] }}" @selected($selectedAction === $option['value'])>{{ $option['key'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="inputs[{{ $index }}][channel]" value="{{ $row['channel'] ?? '' }}">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="inputs[{{ $index }}][mqtt_periodic_updates_enabled]">
                                <option value="inherit" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? 'inherit') === 'inherit')>Inherit</option>
                                <option value="1" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? '') === '1')>Enabled</option>
                                <option value="0" @selected((string) ($row['mqtt_periodic_updates_enabled'] ?? '') === '0')>Disabled</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" min="1" class="form-control form-control-sm" name="inputs[{{ $index }}][mqtt_periodic_update_frequency_seconds]" value="{{ $row['mqtt_periodic_update_frequency_seconds'] ?? '' }}" placeholder="e.g. 60">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="inputs[{{ $index }}][signal_reversed]">
                                <option value="0" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '0')>No</option>
                                <option value="1" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '1')>Yes</option>
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="inputs[{{ $index }}][config_json]" value="{{ $row['config_json'] ?? '{}' }}">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-sensor-binding-row">Remove</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text mt-2">Sensor inputs currently support the <strong>SensorState</strong> action key.</div>
</div>

<template id="sensor-input-binding-template">
    <tr>
        <td>
            <input type="hidden" name="inputs[__INDEX__][enabled]" value="0">
            <input class="form-check-input" type="checkbox" name="inputs[__INDEX__][enabled]" value="1" checked>
        </td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][source_id]">
                <option value="">None</option>
                @foreach ($accessSources as $source)
                    <option value="{{ $source->id }}">{{ $source->name }} ({{ strtoupper($source->type) }})</option>
                @endforeach
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][adapter_type]">
                <option value=""></option>
                @foreach ($adapterOptions as $adapter)
                    <option value="{{ $adapter['value'] }}">{{ $adapter['label'] }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][action_key]">
                <option value=""></option>
                @foreach ($actionOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['key'] }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" name="inputs[__INDEX__][channel]">
        </td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][mqtt_periodic_updates_enabled]">
                <option value="inherit" selected>Inherit</option>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
            </select>
        </td>
        <td><input type="number" min="1" class="form-control form-control-sm" name="inputs[__INDEX__][mqtt_periodic_update_frequency_seconds]" placeholder="e.g. 60"></td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][signal_reversed]">
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="inputs[__INDEX__][config_json]" value="{}">
            <button type="button" class="btn btn-sm btn-outline-danger remove-sensor-binding-row">Remove</button>
        </td>
    </tr>
</template>

<script>
    (() => {
        const tableBody = document.querySelector('#sensor-inputs-table tbody');
        const template = document.getElementById('sensor-input-binding-template');
        const addButton = document.getElementById('add-sensor-input-binding');

        if (!tableBody || !template || !addButton) {
            return;
        }

        const computeNextIndex = () => {
            let maxIndex = -1;
            tableBody.querySelectorAll('[name^="inputs["]').forEach((element) => {
                const name = element.getAttribute('name') || '';
                const match = name.match(/^inputs\[(\d+)\]/);
                if (match) {
                    maxIndex = Math.max(maxIndex, Number(match[1]));
                }
            });

            return maxIndex + 1;
        };

        let nextIndex = computeNextIndex();

        addButton.addEventListener('click', () => {
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
            nextIndex += 1;
            tableBody.insertAdjacentHTML('beforeend', html);
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof HTMLElement) || !event.target.classList.contains('remove-sensor-binding-row')) {
                return;
            }

            const row = event.target.closest('tr');
            if (row) {
                row.remove();
            }
        });
    })();
</script>
