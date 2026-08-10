@php
    $configJson = old('config_json');
    if ($configJson === null) {
        $configJson = json_encode($accessSwitch?->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessSwitch?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessSwitch?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-6">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessSwitch?->identifier ?? '') }}" maxlength="255">
        <div class="form-text">Optional. Leave blank to auto-slug from switch name.</div>
    </div>

    <div class="col-md-8">
        <label for="area_id" class="form-label">Area</label>
        <select class="form-select" id="area_id" name="area_id" required>
            <option value="">Select area</option>
            @foreach ($accessAreas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $accessSwitch?->area_id ?? '') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
            @endforeach
        </select>
    </div>

    <input type="hidden" id="config_json" name="config_json" value="{{ $configJson }}">
    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>
