@php
    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessAreaPermission?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div class="row g-3">
    <div class="col-md-5">
        <label for="individual_id" class="form-label">Access User</label>
        <select class="form-select" id="individual_id" name="individual_id" required>
            <option value="">Select user</option>
            @foreach ($accessUsers as $user)
                <option value="{{ $user->id }}" @selected((string) old('individual_id', $accessAreaPermission?->individual_id ?? '') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label for="area_id" class="form-label">Area</label>
        <select class="form-select" id="area_id" name="area_id" required>
            <option value="">Select area</option>
            @foreach ($accessAreas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $accessAreaPermission?->area_id ?? '') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-2">
        <label for="permission" class="form-label">Permission</label>
        <select class="form-select" id="permission" name="permission" required>
            <option value="allow" @selected(old('permission', $accessAreaPermission?->permission ?? 'allow') === 'allow')>Allow</option>
            <option value="deny" @selected(old('permission', $accessAreaPermission?->permission ?? 'allow') === 'deny')>Deny</option>
        </select>
    </div>

    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">
</div>
