@php
    $configJson = old('config_json');
    if ($configJson === null) {
        $configJson = json_encode($accessBinding?->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessBinding?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $selectedTargetType = old('target_type', $accessBinding?->target_type ?? 'reader');
    $selectedTargetId = (string) old('target_id', $accessBinding?->target_id ?? '');
    $inputActionOptions = $inputActionOptions ?? \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::options('input');
    $outputActionOptions = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::options('output');
    $sensorInputActionOptions = $sensorInputActionOptions ?? array_values(array_filter(
        $inputActionOptions,
        static fn (array $option): bool => ($option['value'] ?? null) === \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::SENSOR_STATE->value,
    ));
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label for="direction" class="form-label">Direction</label>
        <select class="form-select" id="direction" name="direction" required>
            <option value="input" @selected(old('direction', $accessBinding?->direction ?? '') === 'input')>Input</option>
            <option value="output" @selected(old('direction', $accessBinding?->direction ?? '') === 'output')>Output</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="adapter_type" class="form-label">Adapter Type</label>
        <select class="form-select" id="adapter_type" name="adapter_type" required>
            @foreach (($adapterTypeOptions ?? []) as $adapter)
                <option value="{{ $adapter['value'] }}" @selected(old('adapter_type', $accessBinding?->adapter_type ?? '') === $adapter['value'])>{{ $adapter['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label for="target_type" class="form-label">Target Type</label>
        <select class="form-select" id="target_type" name="target_type" required>
            <option value="reader" @selected($selectedTargetType === 'reader')>Reader</option>
            <option value="lock" @selected($selectedTargetType === 'lock')>Lock</option>
            <option value="area" @selected($selectedTargetType === 'area')>Area</option>
            <option value="switch" @selected($selectedTargetType === 'switch')>Switch</option>
            <option value="sensor" @selected($selectedTargetType === 'sensor')>Sensor</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="target_id" class="form-label">Target</label>
        <select class="form-select" id="target_id" name="target_id" required></select>
    </div>

    <div class="col-md-4">
        <label for="source_id" class="form-label">Source</label>
        <select class="form-select" id="source_id" name="source_id">
            <option value="">None</option>
            @foreach ($accessSources as $source)
                <option value="{{ $source->id }}" @selected((string) old('source_id', $accessBinding?->source_id ?? '') === (string) $source->id)>{{ $source->name }} ({{ strtoupper($source->type) }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="action_key" class="form-label">Action Key</label>
        @php
            $selectedAction = \OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey::fromStored(old('action_key', $accessBinding?->action_key));
        @endphp
        <select class="form-select" id="action_key" name="action_key" required>
            <option value="">Select action</option>
            @foreach (($actionOptions ?? []) as $option)
                <option value="{{ $option['value'] }}" @selected($selectedAction?->value === $option['value'])>{{ $option['key'] }} - {{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="channel" class="form-label">Channel/Tag</label>
        <input type="text" class="form-control" id="channel" name="channel" value="{{ old('channel', $accessBinding?->channel ?? '') }}" maxlength="255" placeholder="e.g. 2">
        <div id="channel_help" class="form-text">For Modbus: input bindings read DI channels; output bindings write relay coil channels. Use numeric channel (1-based) or addr:&lt;address&gt;.</div>
    </div>

    <div class="col-md-3">
        <label for="signal_reversed" class="form-label">Signal Reversed</label>
        <select class="form-select" id="signal_reversed" name="signal_reversed" required>
            <option value="0" @selected((string) old('signal_reversed', ($accessBinding?->signal_reversed ?? false) ? '1' : '0') === '0')>No</option>
            <option value="1" @selected((string) old('signal_reversed', ($accessBinding?->signal_reversed ?? false) ? '1' : '0') === '1')>Yes</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="enabled" class="form-label">Enabled</label>
        <select class="form-select" id="enabled" name="enabled" required>
            <option value="1" @selected((string) old('enabled', ($accessBinding?->enabled ?? true) ? '1' : '0') === '1')>Yes</option>
            <option value="0" @selected((string) old('enabled', ($accessBinding?->enabled ?? true) ? '1' : '0') === '0')>No</option>
        </select>
    </div>

    <input type="hidden" id="config_json" name="config_json" value="{{ $configJson }}">
    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>

<script>
    (() => {
        const directionEl = document.getElementById('direction');
        const adapterTypeEl = document.getElementById('adapter_type');
        const actionKeyEl = document.getElementById('action_key');
        const channelEl = document.getElementById('channel');
        const channelHelpEl = document.getElementById('channel_help');
        const targetTypeEl = document.getElementById('target_type');
        const targetIdEl = document.getElementById('target_id');
        if (!targetTypeEl || !targetIdEl) {
            return;
        }

        const selectedTargetId = @json($selectedTargetId);
        const inputActionOptions = @json($inputActionOptions);
        const outputActionOptions = @json($outputActionOptions);
        const sensorInputActionOptions = @json($sensorInputActionOptions);
        const optionsByType = {
            reader: @json($readerTargets->map(fn($item) => ['id' => (string) $item->id, 'label' => $item->name.' ('.$item->identifier.')'])->values()->all()),
            lock: @json($lockTargets->map(fn($item) => ['id' => (string) $item->id, 'label' => $item->name.' ('.$item->identifier.')'])->values()->all()),
            area: @json($areaTargets->map(fn($item) => ['id' => (string) $item->id, 'label' => $item->name.' ('.$item->identifier.')'])->values()->all()),
            switch: @json($switchTargets->map(fn($item) => ['id' => (string) $item->id, 'label' => $item->name.' ('.$item->identifier.')'])->values()->all()),
            sensor: @json($sensorTargets->map(fn($item) => ['id' => (string) $item->id, 'label' => $item->name.' ('.$item->identifier.')'])->values()->all()),
        };

        const renderTargetOptions = () => {
            const targetType = targetTypeEl.value;
            const options = optionsByType[targetType] || [];

            targetIdEl.innerHTML = '';

            options.forEach((option) => {
                const opt = document.createElement('option');
                opt.value = option.id;
                opt.textContent = option.label;
                if (option.id === selectedTargetId) {
                    opt.selected = true;
                }
                targetIdEl.appendChild(opt);
            });

            if (targetIdEl.options.length > 0 && targetIdEl.selectedIndex < 0) {
                targetIdEl.selectedIndex = 0;
            }
        };

        const updateChannelHelp = () => {
            if (!channelEl || !channelHelpEl || !directionEl || !adapterTypeEl) {
                return;
            }

            const adapterType = (adapterTypeEl.value || '').toLowerCase();
            const direction = (directionEl.value || '').toLowerCase();

            if (adapterType === 'modbus') {
                if (direction === 'input') {
                    channelEl.placeholder = 'DI channel (e.g. 2) or addr:1';
                    channelHelpEl.textContent = 'Modbus input bindings read Discrete Inputs (DI). Channel 2 maps to DI2 (or input_start_address + 1).';
                    return;
                }

                if (direction === 'output') {
                    channelEl.placeholder = 'Relay/coil channel (e.g. 2) or addr:1';
                    channelHelpEl.textContent = 'Modbus output bindings write relay coils. Channel 2 maps to CH2 (or coil_start_address + 1).';
                    return;
                }
            }

            channelEl.placeholder = 'e.g. 2';
            channelHelpEl.textContent = 'Use adapter-specific channel/tag value. For Modbus, use numeric channel (1-based) or addr:<address>.';
        };

        const currentSelectedAction = () => {
            if (!actionKeyEl) {
                return '';
            }

            return String(actionKeyEl.value || '');
        };

        const renderActionOptions = () => {
            if (!actionKeyEl || !directionEl || !targetTypeEl) {
                return;
            }

            const previous = currentSelectedAction();
            const direction = (directionEl.value || '').toLowerCase();
            const targetType = (targetTypeEl.value || '').toLowerCase();

            let options = [];
            if (direction === 'output') {
                options = outputActionOptions;
            } else if (targetType === 'sensor') {
                options = sensorInputActionOptions;
            } else {
                options = inputActionOptions;
            }

            actionKeyEl.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select action';
            actionKeyEl.appendChild(placeholder);

            for (const option of options) {
                const opt = document.createElement('option');
                opt.value = String(option.value);
                opt.textContent = `${option.key} - ${option.label}`;
                if (opt.value === previous) {
                    opt.selected = true;
                }
                actionKeyEl.appendChild(opt);
            }
        };

        targetTypeEl.addEventListener('change', () => {
            while (targetIdEl.firstChild) {
                targetIdEl.removeChild(targetIdEl.firstChild);
            }

            const targetType = targetTypeEl.value;
            const options = optionsByType[targetType] || [];
            options.forEach((option, index) => {
                const opt = document.createElement('option');
                opt.value = option.id;
                opt.textContent = option.label;
                if (index === 0) {
                    opt.selected = true;
                }
                targetIdEl.appendChild(opt);
            });

            renderActionOptions();
        });

        directionEl?.addEventListener('change', updateChannelHelp);
        adapterTypeEl?.addEventListener('change', updateChannelHelp);
        directionEl?.addEventListener('change', renderActionOptions);

        renderTargetOptions();
        updateChannelHelp();
        renderActionOptions();
    })();
</script>
