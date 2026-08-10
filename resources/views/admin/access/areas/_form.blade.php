@php
    $configJson = old('config_json');
    if ($configJson === null) {
        $configJson = json_encode($accessArea?->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $metadataJson = old('metadata_json');
    if ($metadataJson === null) {
        $metadataJson = json_encode($accessArea?->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $selectedReaderIds = collect(old('reader_ids', $areaReaderIds ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedLockIds = collect(old('lock_ids', $areaLockIds ?? []))->map(fn ($id) => (string) $id)->all();
    $selectedSwitchIds = collect(old('switch_ids', $areaSwitchIds ?? []))->map(fn ($id) => (string) $id)->all();
    $permissionRows = old('permissions', $areaPermissionRows ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $accessArea?->name ?? '') }}" required maxlength="255">
    </div>

    <div class="col-md-6">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier', $accessArea?->identifier ?? '') }}" maxlength="255">
        <div class="form-text">Optional. Leave blank to auto-slug from area name.</div>
    </div>

    <div class="col-md-3">
        <label for="default_autolock_enabled" class="form-label">Default Auto-lock Enabled</label>
        <select class="form-select" id="default_autolock_enabled" name="default_autolock_enabled" required>
            <option value="1" @selected((string) old('default_autolock_enabled', ($defaultAutolockEnabled ?? false) ? '1' : '0') === '1')>Yes</option>
            <option value="0" @selected((string) old('default_autolock_enabled', ($defaultAutolockEnabled ?? false) ? '1' : '0') === '0')>No</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="default_autolock_duration" class="form-label">Default Auto-lock Duration (s)</label>
        <input type="number" class="form-control" id="default_autolock_duration" name="default_autolock_duration" min="0" value="{{ old('default_autolock_duration', $defaultAutolockDuration ?? 0) }}" required>
    </div>

    <input type="hidden" id="config_json" name="config_json" value="{{ $configJson }}">
    <input type="hidden" id="metadata_json" name="metadata_json" value="{{ $metadataJson }}">

    <div class="col-12 mt-4">
        <h5 class="mb-2">Assign Readers</h5>
        <div class="form-text mb-2">Selected readers will belong to this area. Readers currently in this area but not selected will be unassigned.</div>
        <select class="form-select" name="reader_ids[]" multiple size="8">
            @foreach ($accessReaders as $reader)
                @php
                    $areaLabel = $reader->area_id === null ? 'Unassigned' : ('Area #'.$reader->area_id);
                @endphp
                <option value="{{ $reader->id }}" @selected(in_array((string) $reader->id, $selectedReaderIds, true))>
                    {{ $reader->name }} ({{ $reader->identifier }}) - {{ $areaLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mt-4">
        <h5 class="mb-2">Assign Locks</h5>
        <div class="form-text mb-2">Selected locks will be moved into this area. Locks not selected are not modified.</div>
        <select class="form-select" name="lock_ids[]" multiple size="8">
            @foreach ($accessLocks as $lock)
                @php
                    $areaLabel = $lock->area_id === null ? 'No area' : ('Area #'.$lock->area_id);
                @endphp
                <option value="{{ $lock->id }}" @selected(in_array((string) $lock->id, $selectedLockIds, true))>
                    {{ $lock->name }} ({{ $lock->identifier }}) - {{ $areaLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mt-4">
        <h5 class="mb-2">Assign Switches</h5>
        <div class="form-text mb-2">Selected switches will be moved into this area. Switches not selected are not modified.</div>
        <select class="form-select" name="switch_ids[]" multiple size="8">
            @foreach ($accessSwitches as $switch)
                @php
                    $areaLabel = $switch->area_id === null ? 'No area' : ('Area #'.$switch->area_id);
                @endphp
                <option value="{{ $switch->id }}" @selected(in_array((string) $switch->id, $selectedSwitchIds, true))>
                    {{ $switch->name }} ({{ $switch->identifier }}) - {{ $areaLabel }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Area User Permissions</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-area-permission-row">Add Permission</button>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 align-middle" id="area-permissions-table">
                <thead>
                    <tr>
                        <th>Access User</th>
                        <th>Permission</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionRows as $index => $row)
                        <tr>
                            <td>
                                <select class="form-select form-select-sm" name="permissions[{{ $index }}][individual_id]" required>
                                    <option value="">Select user</option>
                                    @foreach ($accessUsers as $user)
                                        <option value="{{ $user->id }}" @selected((string) ($row['individual_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="permissions[{{ $index }}][permission]" required>
                                    <option value="allow" @selected(($row['permission'] ?? 'allow') === 'allow')>Allow</option>
                                    <option value="deny" @selected(($row['permission'] ?? 'allow') === 'deny')>Deny</option>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="permissions[{{ $index }}][metadata_json]" value="{{ $row['metadata_json'] ?? '{}' }}">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-area-permission-row">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-12 mt-4">
        <div class="card border-secondary-subtle">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Pending Area Summary</span>
                <small class="text-muted">Preview before save</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Readers</strong>
                                <span class="badge text-bg-primary" id="summary-readers-count">0</span>
                            </div>
                            <div id="summary-readers-list" class="small text-muted">No readers selected.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Locks</strong>
                                <span class="badge text-bg-primary" id="summary-locks-count">0</span>
                            </div>
                            <div id="summary-locks-list" class="small text-muted">No locks selected.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Permissions</strong>
                                <span class="badge text-bg-primary" id="summary-permissions-count">0</span>
                            </div>
                            <div id="summary-permissions-breakdown" class="small text-muted mb-2">No permissions configured.</div>
                            <div id="summary-permissions-list" class="small text-muted">No users configured.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="area-permission-row-template">
    <tr>
        <td>
            <select class="form-select form-select-sm" name="permissions[__INDEX__][individual_id]" required>
                <option value="">Select user</option>
                @foreach ($accessUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="permissions[__INDEX__][permission]" required>
                <option value="allow">Allow</option>
                <option value="deny">Deny</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="permissions[__INDEX__][metadata_json]" value="{}">
            <button type="button" class="btn btn-sm btn-outline-danger remove-area-permission-row">Remove</button>
        </td>
    </tr>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const readersSelect = document.querySelector('select[name="reader_ids[]"]');
        const locksSelect = document.querySelector('select[name="lock_ids[]"]');
        const tableBody = document.querySelector('#area-permissions-table tbody');
        const addButton = document.getElementById('add-area-permission-row');
        const template = document.getElementById('area-permission-row-template');

        const readersCount = document.getElementById('summary-readers-count');
        const readersList = document.getElementById('summary-readers-list');
        const locksCount = document.getElementById('summary-locks-count');
        const locksList = document.getElementById('summary-locks-list');
        const permissionsCount = document.getElementById('summary-permissions-count');
        const permissionsBreakdown = document.getElementById('summary-permissions-breakdown');
        const permissionsList = document.getElementById('summary-permissions-list');

        if (!tableBody || !addButton || !template || !readersSelect || !locksSelect || !readersCount || !readersList || !locksCount || !locksList || !permissionsCount || !permissionsBreakdown || !permissionsList) {
            return;
        }

        const findMaxIndex = () => {
            const fields = tableBody.querySelectorAll('[name^="permissions["]');
            let maxIndex = -1;

            fields.forEach((field) => {
                const match = field.getAttribute('name')?.match(/^permissions\[(\d+)\]/);
                if (!match) {
                    return;
                }

                const index = Number.parseInt(match[1], 10);
                if (!Number.isNaN(index)) {
                    maxIndex = Math.max(maxIndex, index);
                }
            });

            return maxIndex;
        };

        let permissionRowIndex = findMaxIndex() + 1;

        const asBadges = (values, badgeClass) => {
            if (!Array.isArray(values) || values.length === 0) {
                return '<span class="text-muted">None</span>';
            }

            return values
                .map((label) => `<span class="badge ${badgeClass} me-1 mb-1">${label}</span>`)
                .join('');
        };

        const refreshSummary = () => {
            const selectedReaders = Array.from(readersSelect.selectedOptions).map((opt) => opt.text.trim());
            readersCount.textContent = String(selectedReaders.length);
            readersList.innerHTML = selectedReaders.length === 0
                ? 'No readers selected.'
                : asBadges(selectedReaders, 'text-bg-light border text-dark');

            const selectedLocks = Array.from(locksSelect.selectedOptions).map((opt) => opt.text.trim());
            locksCount.textContent = String(selectedLocks.length);
            locksList.innerHTML = selectedLocks.length === 0
                ? 'No locks selected.'
                : asBadges(selectedLocks, 'text-bg-light border text-dark');

            const permissionRows = Array.from(tableBody.querySelectorAll('tr'));
            const permissionEntries = permissionRows
                .map((row) => {
                    const userSelect = row.querySelector('select[name*="[individual_id]"]');
                    const permissionSelect = row.querySelector('select[name*="[permission]"]');

                    if (!(userSelect instanceof HTMLSelectElement) || !(permissionSelect instanceof HTMLSelectElement)) {
                        return null;
                    }

                    const userLabel = userSelect.selectedOptions[0]?.text?.trim() ?? '';
                    const permission = permissionSelect.value;

                    if (!userLabel || !permission) {
                        return null;
                    }

                    return {
                        userLabel,
                        permission,
                    };
                })
                .filter((entry) => entry !== null);

            const allowCount = permissionEntries.filter((entry) => entry.permission === 'allow').length;
            const denyCount = permissionEntries.filter((entry) => entry.permission === 'deny').length;

            permissionsCount.textContent = String(permissionEntries.length);
            permissionsBreakdown.innerHTML = permissionEntries.length === 0
                ? 'No permissions configured.'
                : `<span class="badge text-bg-success me-2">Allow: ${allowCount}</span><span class="badge text-bg-danger">Deny: ${denyCount}</span>`;

            permissionsList.innerHTML = permissionEntries.length === 0
                ? 'No users configured.'
                : permissionEntries
                    .map((entry) => `<span class="badge ${entry.permission === 'allow' ? 'text-bg-success' : 'text-bg-danger'} me-1 mb-1">${entry.userLabel} (${entry.permission.toUpperCase()})</span>`)
                    .join('');
        };

        addButton.addEventListener('click', () => {
            const index = permissionRowIndex++;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            tableBody.insertAdjacentHTML('beforeend', html);
            refreshSummary();
        });

        tableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (!target.classList.contains('remove-area-permission-row')) {
                return;
            }

            const row = target.closest('tr');
            if (row) {
                row.remove();
                refreshSummary();
            }
        });

        tableBody.addEventListener('change', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.matches('select[name*="[individual_id]"]') || target.matches('select[name*="[permission]"]')) {
                refreshSummary();
            }
        });

        readersSelect.addEventListener('change', refreshSummary);
        locksSelect.addEventListener('change', refreshSummary);

        refreshSummary();
    });
</script>
