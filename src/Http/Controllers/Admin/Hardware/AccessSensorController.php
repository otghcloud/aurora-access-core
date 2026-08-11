<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlCapabilityRegistry;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;

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

        $sensorBindings = AdapterBinding::query()
            ->where('direction', 'input')
            ->where('target_type', 'sensor')
            ->where('target_id', $sensor->id)
            ->with('source:id,name,identifier')
            ->orderBy('id')
            ->get();

        return view('admin.hardware.sensors.show', [
            'accessSensor' => $sensor,
            'sensorBindings' => $sensorBindings,
        ]);
    }

    public function create(): View
    {
        $sensorActionOptions = array_values(array_filter(
            AccessBindingActionKey::options('input'),
            static fn (array $option): bool => ($option['value'] ?? null) === AccessBindingActionKey::SENSOR_STATE->value,
        ));

        return view('admin.hardware.sensors.create', [
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'accessSources' => Source::query()->orderBy('name')->get(['id', 'name', 'identifier', 'type']),
            'inputBindings' => [],
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'sensorInputActionOptions' => $sensorActionOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $inputBindings = $validated['input_bindings'];
        unset($validated['input_bindings']);

        $sensor = Sensor::create($validated);
        $this->syncSensorBindings($sensor, $inputBindings);

        app(AccessControlMqttPublisher::class)->publishSensorState($sensor, ['force' => true]);

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor created successfully.');
    }

    public function edit(Sensor $sensor): View
    {
        $sensorActionOptions = array_values(array_filter(
            AccessBindingActionKey::options('input'),
            static fn (array $option): bool => ($option['value'] ?? null) === AccessBindingActionKey::SENSOR_STATE->value,
        ));

        $inputBindings = AdapterBinding::query()
            ->where('direction', 'input')
            ->where('target_type', 'sensor')
            ->where('target_id', $sensor->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => [
                'source_id' => $binding->source_id,
                'adapter_type' => app(AccessControlCapabilityRegistry::class)->normalizeBindingAdapterType((string) $binding->adapter_type) ?? (string) $binding->adapter_type,
                'action_key' => AccessBindingActionKey::fromStored($binding->action_key)?->value,
                'channel' => $binding->channel,
                'signal_reversed' => (bool) $binding->signal_reversed,
                'enabled' => (bool) $binding->enabled,
                'mqtt_periodic_updates_enabled' => is_bool(data_get(is_array($binding->config) ? $binding->config : [], 'mqtt_periodic_updates_enabled'))
                    ? (data_get(is_array($binding->config) ? $binding->config : [], 'mqtt_periodic_updates_enabled') ? '1' : '0')
                    : 'inherit',
                'mqtt_periodic_update_frequency_seconds' => is_numeric(data_get(is_array($binding->config) ? $binding->config : [], 'mqtt_periodic_update_frequency_seconds'))
                    ? (int) data_get(is_array($binding->config) ? $binding->config : [], 'mqtt_periodic_update_frequency_seconds')
                    : null,
                'config_json' => (string) json_encode($binding->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ])
            ->values()
            ->all();

        return view('admin.hardware.sensors.edit', [
            'accessSensor' => $sensor,
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'accessSources' => Source::query()->orderBy('name')->get(['id', 'name', 'identifier', 'type']),
            'inputBindings' => $inputBindings,
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'sensorInputActionOptions' => $sensorActionOptions,
        ]);
    }

    public function update(Request $request, Sensor $sensor): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $sensor);

        $inputBindings = $validated['input_bindings'];
        unset($validated['input_bindings']);

        $sensor->update($validated);
        $this->syncSensorBindings($sensor->fresh(), $inputBindings);

        app(AccessControlMqttPublisher::class)->publishSensorState($sensor, ['force' => true]);

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor updated successfully.');
    }

    public function destroy(Sensor $sensor): RedirectResponse
    {
        $sensor->delete();

        return redirect()->route('admin.access-sensors.index')->with('status', 'Sensor deleted successfully.');
    }

    /**
     * @return array{area_id:int,name:string,identifier:string,state:bool,config:array<string,mixed>,metadata:array<string,mixed>,input_bindings:array<int,array<string,mixed>>}
     */
    private function validateAndNormalize(Request $request, ?Sensor $sensor = null): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

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
            'inputs' => ['sometimes', 'array'],
            'inputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'inputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'inputs.*.action_key' => ['nullable'],
            'inputs.*.channel' => ['nullable', 'string', 'max:255'],
            'inputs.*.signal_reversed' => ['nullable', 'boolean'],
            'inputs.*.enabled' => ['nullable', 'boolean'],
            'inputs.*.config_json' => ['nullable', 'string'],
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
            'input_bindings' => $this->normalizeInputBindingRows((array) ($validated['inputs'] ?? [])),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeInputBindingRows(array $rows): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);
        $normalized = [];

        foreach ($rows as $index => $row) {
            $adapterTypeRaw = strtolower(trim((string) ($row['adapter_type'] ?? '')));
            $adapterType = $capabilities->normalizeBindingAdapterType($adapterTypeRaw) ?? $adapterTypeRaw;
            $resolvedAction = AccessBindingActionKey::fromStored($row['action_key'] ?? null);
            $actionKey = $resolvedAction?->value;
            $channel = trim((string) ($row['channel'] ?? ''));
            $sourceId = isset($row['source_id']) && $row['source_id'] !== '' ? (int) $row['source_id'] : null;

            $isEmpty = $adapterType === '' && $actionKey === null && $channel === '' && $sourceId === null;
            if ($isEmpty) {
                continue;
            }

            if ($adapterType === '' || ! $resolvedAction instanceof AccessBindingActionKey) {
                throw ValidationException::withMessages([
                    "inputs.$index" => 'Adapter Type and Action Key are required when a row is used.',
                ]);
            }

            if (! $resolvedAction->isInputAction()) {
                throw ValidationException::withMessages([
                    "inputs.$index.action_key" => 'Selected action key is not valid for input bindings.',
                ]);
            }

            $configJson = trim((string) ($row['config_json'] ?? ''));
            $config = [];
            if ($configJson !== '') {
                $decoded = json_decode($configJson, true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        "inputs.$index.config_json" => 'Config JSON must be valid JSON object syntax.',
                    ]);
                }
                $config = $decoded;
            }

            $periodicMode = strtolower(trim((string) ($row['mqtt_periodic_updates_enabled'] ?? 'inherit')));
            if (in_array($periodicMode, ['1', '0'], true)) {
                $config['mqtt_periodic_updates_enabled'] = $periodicMode === '1';
            } else {
                unset($config['mqtt_periodic_updates_enabled']);
            }

            $periodicFrequencyRaw = trim((string) ($row['mqtt_periodic_update_frequency_seconds'] ?? ''));
            if ($periodicFrequencyRaw !== '' && is_numeric($periodicFrequencyRaw)) {
                $config['mqtt_periodic_update_frequency_seconds'] = max(1, (int) $periodicFrequencyRaw);
            } else {
                unset($config['mqtt_periodic_update_frequency_seconds']);
            }

            $normalized[] = [
                'source_id' => $sourceId,
                'adapter_type' => $adapterType,
                'action_key' => $actionKey,
                'channel' => $channel !== '' ? $channel : null,
                'signal_reversed' => (bool) ($row['signal_reversed'] ?? false),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'config' => $config,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array<string,mixed>>  $inputRows
     */
    private function syncSensorBindings(Sensor $sensor, array $inputRows): void
    {
        AdapterBinding::query()
            ->where('target_type', 'sensor')
            ->where('target_id', $sensor->id)
            ->delete();

        foreach ($inputRows as $row) {
            AdapterBinding::create([
                'direction' => 'input',
                'adapter_type' => (string) $row['adapter_type'],
                'target_type' => 'sensor',
                'target_id' => $sensor->id,
                'source_id' => $row['source_id'],
                'action_key' => (int) $row['action_key'],
                'channel' => $row['channel'],
                'signal_reversed' => (bool) $row['signal_reversed'],
                'enabled' => (bool) $row['enabled'],
                'config' => is_array($row['config'] ?? null) ? $row['config'] : [],
                'metadata' => [],
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
}
