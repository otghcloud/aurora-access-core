<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Access;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\DataTables\Admin\AreaPermissionsDataTable;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Individual;

class AreaPermissionController extends Controller
{
    public function index(AreaPermissionsDataTable $dataTable)
    {
        return $dataTable->render('admin.access.permissions.index', [
            'accessUsers' => Individual::query()->orderBy('name', 'asc')->get(['id', 'name']),
            'accessAreas' => Area::query()->orderBy('name', 'asc')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function create(): View
    {
        return view('admin.access.permissions.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $this->ensureNoDuplicate($validated['individual_id'], $validated['area_id']);

        AreaPermission::create($validated);

        return redirect()->route('admin.access-area-permissions.index')
            ->with('status', 'Area permission created successfully.');
    }

    public function edit(AreaPermission $areaPermission): View
    {
        return view('admin.access.permissions.edit', $this->formData($areaPermission));
    }

    public function update(Request $request, AreaPermission $areaPermission): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $this->ensureNoDuplicate($validated['individual_id'], $validated['area_id'], $areaPermission->id);

        $areaPermission->update($validated);

        return redirect()->route('admin.access-area-permissions.index')
            ->with('status', 'Area permission updated successfully.');
    }

    public function destroy(AreaPermission $areaPermission): RedirectResponse
    {
        AreaPermission::query()->whereKey($areaPermission->id)->delete();

        return redirect()->route('admin.access-area-permissions.index')
            ->with('status', 'Area permission deleted successfully.');
    }

    /**
     * @return array{individual_id:int,area_id:int,permission:string,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request): array
    {
        $validated = $request->validate([
            'individual_id' => ['required', 'integer', Rule::exists('individuals', 'id')],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'permission' => ['required', 'string', 'in:allow,deny'],
            'metadata_json' => ['nullable', 'string'],
        ]);

        return [
            'individual_id' => (int) $validated['individual_id'],
            'area_id' => (int) $validated['area_id'],
            'permission' => $validated['permission'],
            'metadata' => $this->decodeJsonField($validated['metadata_json'] ?? null, 'metadata_json'),
        ];
    }

    private function ensureNoDuplicate(int $userId, int $areaId, ?int $ignoreId = null): void
    {
        $query = AreaPermission::query()
            ->where('individual_id', $userId)
            ->where('area_id', $areaId);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'area_id' => 'This user already has a permission entry for the selected area.',
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonField(?string $json, string $field): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON object syntax.',
            ]);
        }

        return $decoded;
    }

    /**
     * @return array<string,mixed>
     */
    private function formData(?AreaPermission $areaPermission = null): array
    {
        return [
            'accessAreaPermission' => $areaPermission,
            'accessUsers' => Individual::query()->orderBy('name', 'asc')->get(['id', 'name']),
            'accessAreas' => Area::query()->orderBy('name', 'asc')->get(['id', 'name', 'identifier']),
        ];
    }
}
