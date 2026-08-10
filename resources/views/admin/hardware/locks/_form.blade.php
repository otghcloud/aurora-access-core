@php
    $configJson = old('config_json');
    if ($configJson === null) {
        $configJson = json_encode($accessLock?->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessLock?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $overrideEnabledValue = old('override_autolock_enabled');
    if ($overrideEnabledValue === null) {
        $existingOverride = data_get($accessLock?->config, 'locking.autolock_override_enabled');
        $overrideEnabledValue = $existingOverride === null ? 'inherit' : ((bool) $existingOverride ? '1' : '0');
    }

    $overrideDurationValue = old('override_autolock_duration');
    if ($overrideDurationValue === null) {
        $duration = data_get($accessLock?->config, 'locking.autolock_override_duration');
        $overrideDurationValue = $duration === null ? '' : (string) $duration;
    }
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessLock?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-6">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessLock?->identifier ?? '') }}" maxlength="255">
        <div class="form-text">Optional. Leave blank to auto-slug from lock name.</div>
    </div>

    <div class="col-md-8">
        <label for="area_id" class="form-label">Area</label>
        <select class="form-select" id="area_id" name="area_id" required>
            <option value="">Select area</option>
            @foreach ($accessAreas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $accessLock?->area_id ?? '') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="is_primary" class="form-label">Primary Lock</label>
        <select class="form-select" id="is_primary" name="is_primary" required>
            <option value="0" @selected((string) old('is_primary', ($accessLock?->is_primary ?? false) ? '1' : '0') === '0')>No</option>
            <option value="1" @selected((string) old('is_primary', ($accessLock?->is_primary ?? false) ? '1' : '0') === '1')>Yes</option>
        </select>
        <div class="form-text">Setting Yes will unset other primary locks in this area.</div>
    </div>

    <div class="col-md-4">
        <label for="override_autolock_enabled" class="form-label">Auto-lock Enabled Override</label>
        <select class="form-select" id="override_autolock_enabled" name="override_autolock_enabled">
            <option value="inherit" @selected((string) $overrideEnabledValue === 'inherit')>Inherit Area Default</option>
            <option value="1" @selected((string) $overrideEnabledValue === '1')>Force Enabled</option>
            <option value="0" @selected((string) $overrideEnabledValue === '0')>Force Disabled</option>
        </select>
    </div>

    <div class="col-md-4">
        <label for="override_autolock_duration" class="form-label">Auto-lock Duration Override (s)</label>
        <input type="number" class="form-control" id="override_autolock_duration" name="override_autolock_duration" min="0" value="{{ $overrideDurationValue }}">
        <div class="form-text">Leave blank to inherit area default duration.</div>
    </div>

    <input type="hidden" id="config_json" name="config_json" value="{{ $configJson }}">
    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>
