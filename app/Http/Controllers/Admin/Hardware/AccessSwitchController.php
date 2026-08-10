<?php

namespace App\Http\Controllers\Admin\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Access\Area;
use App\Models\Hardware\PhysicalSwitch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessSwitchController extends Controller
{
    public function index(): View
    {
        return view('admin.hardware.switches.index', [
            'accessSwitches' => PhysicalSwitch::query()->with('area')->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.hardware.switches.create', [
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        PhysicalSwitch::create($validated);

        return redirect()->route('admin.access-switches.index')->with('status', 'Switch created successfully.');
    }

    public function edit(PhysicalSwitch $switch): View
    {
        return view('admin.hardware.switches.edit', [
            'accessSwitch' => $switch,
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function update(Request $request, PhysicalSwitch $switch): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $switch);

        $switch->update($validated);

        return redirect()->route('admin.access-switches.index')->with('status', 'Switch updated successfully.');
    }

    public function destroy(PhysicalSwitch $switch): RedirectResponse
    {
        $switch->delete();

        return redirect()->route('admin.access-switches.index')->with('status', 'Switch deleted successfully.');
    }

    /**
     * @return array{area_id:int,name:string,identifier:string,config:array<string,mixed>,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request, ?PhysicalSwitch $switch = null): array
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('switches', 'identifier')->ignore($switch?->id),
            ],
            'config_json' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'string'],
        ]);

        $identifier = trim((string) ($validated['identifier'] ?? ''));
        if ($identifier === '') {
            $identifier = Str::slug($validated['name'], '-');
        }

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => 'Identifier cannot be empty after normalization.',
            ]);
        }

        return [
            'area_id' => (int) $validated['area_id'],
            'name' => $validated['name'],
            'identifier' => $identifier,
            'config' => $this->decodeJsonField($validated['config_json'] ?? null, 'config_json'),
            'metadata' => $this->decodeJsonField($validated['metadata_json'] ?? null, 'metadata_json'),
        ];
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
}
