@php
    $configJson = old('config_json');
    if ($configJson === null) {
        $configJson = json_encode($accessSensor?->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessSensor?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessSensor?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-6">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessSensor?->identifier ?? '') }}" maxlength="255">
        <div class="form-text">Optional. Leave blank to auto-slug from sensor name.</div>
    </div>

    <div class="col-md-6">
        <label for="area_id" class="form-label">Area</label>
        <select class="form-select" id="area_id" name="area_id" required>
            <option value="">Select area</option>
            @foreach ($accessAreas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $accessSensor?->area_id ?? '') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="state" class="form-label">State</label>
        <select class="form-select" id="state" name="state" required>
            <option value="0" @selected((string) old('state', ($accessSensor?->state ?? false) ? '1' : '0') === '0')>Inactive</option>
            <option value="1" @selected((string) old('state', ($accessSensor?->state ?? false) ? '1' : '0') === '1')>Active</option>
        </select>
    </div>

    <input type="hidden" id="config_json" name="config_json" value="{{ $configJson }}">
    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>

@include('admin.hardware.sensors._bindings_form')
