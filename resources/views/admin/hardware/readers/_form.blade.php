@php
    $config = $accessReader?->config ?? [];
    $metadata = $accessReader?->metadata ?? [];

    $inputRows = old('inputs', $inputBindings ?? []);
    $outputRows = old('outputs', $outputBindings ?? []);

    $adapterOptions = $adapterTypeOptions ?? [];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessReader?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-4">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessReader?->identifier ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-4">
        <label for="area_id" class="form-label">Area</label>
        <select class="form-select" id="area_id" name="area_id">
            <option value="">Unassigned</option>
            @foreach ($accessAreas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $accessReader->area_id ?? '') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mt-3">
        <h6 class="mb-2">General</h6>
    </div>

    <div class="col-md-6">
        <div class="alert alert-info mb-0">
            Auto-lock policy is configured on the area (default) and can be overridden per lock.
        </div>
    </div>

    <div class="col-md-3">
        <label for="general_feedback_state_duration" class="form-label">Feedback Duration (s)</label>
        <input type="number" class="form-control" id="general_feedback_state_duration" name="general_feedback_state_duration" min="0" value="{{ old('general_feedback_state_duration', data_get($config, 'general.feedback_state_duration', 5)) }}" required>
    </div>

    <div class="col-md-3">
        <label for="general_reader_mode" class="form-label">Reader Mode</label>
        <select class="form-select" id="general_reader_mode" name="general_reader_mode">
            <option value="card_only" @selected(old('general_reader_mode', data_get($config, 'general.reader_mode', 'card_only')) === 'card_only')>Card Only</option>
            <option value="keypad" @selected(old('general_reader_mode', data_get($config, 'general.reader_mode', 'card_only')) === 'keypad')>Keypad</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="general_input_format" class="form-label">Input Format</label>
        <select class="form-select" id="general_input_format" name="general_input_format">
            <option value="wiegand" @selected(old('general_input_format', data_get($config, 'general.input_format', 'wiegand')) === 'wiegand')>Wiegand</option>
        </select>
    </div>

    <div class="col-12 mt-4">
        <h6 class="mb-2">Serial Reader</h6>
    </div>

    <div class="col-md-4">
        <label for="wiegand_device" class="form-label">Device Path</label>
        <input type="text" class="form-control" id="wiegand_device" name="wiegand_device" value="{{ old('wiegand_device', data_get($config, 'wiegand.device', $accessReader ? '/dev/'.$accessReader->identifier : '')) }}" maxlength="255">
        <div class="form-text">Defaults to <code>/dev/{identifier}</code> if left blank.</div>
    </div>

    <div class="col-md-2">
        <label for="wiegand_baud_rate" class="form-label">Baud Rate</label>
        <input type="number" class="form-control" id="wiegand_baud_rate" name="wiegand_baud_rate" min="1" value="{{ old('wiegand_baud_rate', data_get($config, 'wiegand.baud_rate', 9600)) }}">
    </div>

    <div class="col-md-2">
        <label for="wiegand_timeout" class="form-label">Read Timeout (s)</label>
        <input type="number" class="form-control" id="wiegand_timeout" name="wiegand_timeout" min="0.1" step="0.1" value="{{ old('wiegand_timeout', data_get($config, 'wiegand.timeout', 1.0)) }}">
    </div>

    <div class="col-md-2">
        <label for="wiegand_duplicate_window" class="form-label">Duplicate Window (s)</label>
        <input type="number" class="form-control" id="wiegand_duplicate_window" name="wiegand_duplicate_window" min="0" step="0.1" value="{{ old('wiegand_duplicate_window', data_get($config, 'wiegand.duplicate_window', 2.0)) }}">
    </div>

    <div class="col-md-2">
        <label for="wiegand_doorbell_duplicate_window" class="form-label">Doorbell Dedup (s)</label>
        <input type="number" class="form-control" id="wiegand_doorbell_duplicate_window" name="wiegand_doorbell_duplicate_window" min="0" step="0.1" value="{{ old('wiegand_doorbell_duplicate_window', data_get($config, 'wiegand.doorbell_duplicate_window', 2.0)) }}">
    </div>

    <div class="col-md-3">
        <label for="wiegand_keypad_timeout" class="form-label">Keypad Timeout (s)</label>
        <input type="number" class="form-control" id="wiegand_keypad_timeout" name="wiegand_keypad_timeout" min="0.1" step="0.1" value="{{ old('wiegand_keypad_timeout', data_get($config, 'wiegand.keypad_timeout', 3.0)) }}">
    </div>

    <div class="col-md-3">
        <label for="wiegand_card_min_value" class="form-label">Card Min Value</label>
        <input type="number" class="form-control" id="wiegand_card_min_value" name="wiegand_card_min_value" min="0" value="{{ old('wiegand_card_min_value', data_get($config, 'wiegand.card_min_value', 15)) }}">
    </div>

    <div class="col-md-3">
        <label for="wiegand_doorbell_value" class="form-label">Doorbell Value</label>
        <input type="number" class="form-control" id="wiegand_doorbell_value" name="wiegand_doorbell_value" min="0" value="{{ old('wiegand_doorbell_value', data_get($config, 'wiegand.doorbell_value', 11)) }}">
    </div>

    <div class="col-12 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Input Bindings</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-input-binding">Add Input</button>
        </div>
        <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 align-middle" id="inputs-table">
                <thead>
                    <tr>
                        <th>Enabled</th>
                        <th>Source</th>
                        <th>Adapter</th>
                        <th>Action</th>
                        <th>Channel/Tag</th>
                        <th>Signal Reversed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inputRows as $index => $row)
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
                                    $selectedInputAction = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored($row['action_key'] ?? null)?->value;
                                @endphp
                                <select class="form-select form-select-sm" name="inputs[{{ $index }}][action_key]">
                                    <option value=""></option>
                                    @foreach (($inputActionOptions ?? []) as $option)
                                        <option value="{{ $option['value'] }}" @selected($selectedInputAction === $option['value'])>{{ $option['key'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="inputs[{{ $index }}][channel]" value="{{ $row['channel'] ?? '' }}">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="inputs[{{ $index }}][signal_reversed]">
                                    <option value="0" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '0')>No</option>
                                    <option value="1" @selected((string) ($row['signal_reversed'] ?? false ? '1' : '0') === '1')>Yes</option>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="inputs[{{ $index }}][config_json]" value="{{ $row['config_json'] ?? '{}' }}">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-binding-row">Remove</button>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-12 mt-4">
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
                        <th>Signal Reversed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outputRows as $index => $row)
                        <tr>
                            <td>
                                <input type="hidden" name="outputs[{{ $index }}][enabled]" value="0">
                                <input class="form-check-input" type="checkbox" name="outputs[{{ $index }}][enabled]" value="1" @checked((bool) ($row['enabled'] ?? true))>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="outputs[{{ $index }}][source_id]">
                                    <option value="">None</option>
                                    @foreach ($accessSources as $source)
                                        <option value="{{ $source->id }}" @selected((string) ($row['source_id'] ?? '') === (string) $source->id)>{{ $source->name }} ({{ strtoupper($source->type) }})</option>
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
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="form-text">Reader outputs are reader-scoped. Manage lock outputs from the lock details page.</div>
    </div>

    <div class="col-12 mt-4">
        <h6 class="mb-2">Metadata</h6>
    </div>

    <div class="col-md-3">
        <label for="metadata_reader_model" class="form-label">Reader Model</label>
        <input type="text" class="form-control" id="metadata_reader_model" name="metadata_reader_model" value="{{ old('metadata_reader_model', data_get($metadata, 'reader.model', '')) }}" maxlength="255">
    </div>

    <div class="col-md-3">
        <label for="metadata_reader_type" class="form-label">Reader Type</label>
        <input type="text" class="form-control" id="metadata_reader_type" name="metadata_reader_type" value="{{ old('metadata_reader_type', data_get($metadata, 'reader.type', '')) }}" maxlength="255">
    </div>

    <div class="col-md-3">
        <label for="metadata_lock_model" class="form-label">Lock Model</label>
        <input type="text" class="form-control" id="metadata_lock_model" name="metadata_lock_model" value="{{ old('metadata_lock_model', data_get($metadata, 'lock.model', '')) }}" maxlength="255">
    </div>

    <div class="col-md-3">
        <label for="metadata_lock_type" class="form-label">Lock Type</label>
        <input type="text" class="form-control" id="metadata_lock_type" name="metadata_lock_type" value="{{ old('metadata_lock_type', data_get($metadata, 'lock.type', '')) }}" maxlength="255">
    </div>
</div>

<template id="input-binding-template">
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
                @foreach (($inputActionOptions ?? []) as $option)
                    <option value="{{ $option['value'] }}">{{ $option['key'] }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="inputs[__INDEX__][channel]"></td>
        <td>
            <select class="form-select form-select-sm" name="inputs[__INDEX__][signal_reversed]">
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="inputs[__INDEX__][config_json]" value="{}">
            <button type="button" class="btn btn-sm btn-outline-danger remove-binding-row">Remove</button>
        </td>
    </tr>
</template>

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
        const nextIndexByTable = {};

        const computeInitialIndex = (tableId, prefix) => {
            const tableBody = document.querySelector(`#${tableId} tbody`);
            if (!tableBody) {
                return 0;
            }

            let maxIndex = -1;
            tableBody.querySelectorAll(`[name^="${prefix}["]`).forEach((element) => {
                const name = element.getAttribute('name') || '';
                const match = name.match(new RegExp(`^${prefix}\\[(\\d+)\\]`));
                if (match) {
                    maxIndex = Math.max(maxIndex, Number(match[1]));
                }
            });

            return maxIndex + 1;
        };

        const addRow = (tableId, templateId, prefix) => {
            const tableBody = document.querySelector(`#${tableId} tbody`);
            const template = document.querySelector(`#${templateId}`);
            if (!tableBody || !template) {
                return;
            }

            if (typeof nextIndexByTable[tableId] === 'undefined') {
                nextIndexByTable[tableId] = computeInitialIndex(tableId, prefix);
            }

            const nextIndex = nextIndexByTable[tableId];
            nextIndexByTable[tableId] += 1;

            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
            tableBody.insertAdjacentHTML('beforeend', html);
        };

        document.addEventListener('click', (event) => {
            if (event.target instanceof HTMLElement && event.target.id === 'add-input-binding') {
                addRow('inputs-table', 'input-binding-template', 'inputs');
            }

            if (event.target instanceof HTMLElement && event.target.id === 'add-output-binding') {
                addRow('outputs-table', 'output-binding-template', 'outputs');
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
