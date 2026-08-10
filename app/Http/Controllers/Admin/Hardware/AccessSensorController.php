<?php

namespace App\Http\Controllers\Admin\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Access\Area;
use App\Models\Hardware\Sensor;
use App\Services\AccessControlMqttPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessSensorController extends Controller
{
    public function index(): View
    {
        return view('admin.hardware.sensors.index', [
            'accessSensors' => Sensor::query()->with('area')->latest('id')->paginate(20),
        ]);
    }

    public function show(Sensor $sensor): View
    {
        $sensor->loadMissing('area');

        return view('admin.hardware.sensors.show', [
            'accessSensor' => $sensor,
        ]);
    }

    public function create(): View
    {
        return view('admin.hardware.sensors.create', [
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);
        $sensor = Sensor::create($validated);

        app(AccessControlMqttPublisher::class)->publishSensorState($sensor);

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor created successfully.');
    }

    public function edit(Sensor $sensor): View
    {
        return view('admin.hardware.sensors.edit', [
            'accessSensor' => $sensor,
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function update(Request $request, Sensor $sensor): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $sensor);
        $sensor->update($validated);

        app(AccessControlMqttPublisher::class)->publishSensorState($sensor);

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor updated successfully.');
    }

    public function destroy(Sensor $sensor): RedirectResponse
    {
        $sensor->delete();

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor deleted successfully.');
    }

    /**
     * @return array{area_id:int,name:string,identifier:string,state:bool,config:array<string,mixed>,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request, ?Sensor $sensor = null): array
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('sensors', 'identifier')->ignore($sensor?->id),
            ],
            'state' => ['nullable', 'boolean'],
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
            'state' => (bool) ($validated['state'] ?? false),
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
