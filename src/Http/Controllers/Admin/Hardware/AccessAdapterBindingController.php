<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\PhysicalSwitch;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlCapabilityRegistry;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;

class AccessAdapterBindingController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdapterBinding::query()->with('source');
        $selectedAction = AccessBindingActionKey::fromStored($request->input('action_key'));

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction')->toString());
        }

        if ($request->filled('adapter_type')) {
            $adapterType = app(AccessControlCapabilityRegistry::class)
                ->normalizeBindingAdapterType($request->string('adapter_type')->toString());

            if ($adapterType !== null && $adapterType !== '') {
                $query->where('adapter_type', $adapterType);
            }
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->string('target_type')->toString());
        }

        if ($request->filled('target_id')) {
            $query->where('target_id', (int) $request->input('target_id'));
        }

        if ($request->filled('source_id')) {
            $query->where('source_id', (int) $request->input('source_id'));
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', (bool) $request->boolean('enabled'));
        }

        if ($request->filled('action_key')) {
            if ($selectedAction instanceof AccessBindingActionKey) {
                $query->whereIn('action_key', $selectedAction->queryCandidates());
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $bindings = $query->latest('id')->paginate(30)->withQueryString();

        $readerIds = [];
        $lockIds = [];
        $areaIds = [];
        $switchIds = [];
        $sensorIds = [];

        foreach ($bindings->items() as $binding) {
            if ($binding->target_type === 'reader') {
                $readerIds[] = (int) $binding->target_id;
            }

            if ($binding->target_type === 'lock') {
                $lockIds[] = (int) $binding->target_id;
            }

            if ($binding->target_type === 'area') {
                $areaIds[] = (int) $binding->target_id;
            }

            if ($binding->target_type === 'switch') {
                $switchIds[] = (int) $binding->target_id;
            }

            if ($binding->target_type === 'sensor') {
                $sensorIds[] = (int) $binding->target_id;
            }
        }

        $readerMap = Reader::query()
            ->whereIn('id', array_values(array_unique($readerIds)))
            ->get(['id', 'name', 'identifier'])
            ->mapWithKeys(fn (Reader $reader): array => [
                'reader:'.$reader->id => $reader->name.' ('.$reader->identifier.')',
            ])
            ->all();

        $lockMap = Lock::query()
            ->whereIn('id', array_values(array_unique($lockIds)))
            ->get(['id', 'name', 'identifier'])
            ->mapWithKeys(fn (Lock $lock): array => [
                'lock:'.$lock->id => $lock->name.' ('.$lock->identifier.')',
            ])
            ->all();

        $areaMap = Area::query()
            ->whereIn('id', array_values(array_unique($areaIds)))
            ->get(['id', 'name', 'identifier'])
            ->mapWithKeys(fn (Area $area): array => [
                'area:'.$area->id => $area->name.' ('.$area->identifier.')',
            ])
            ->all();

        $switchMap = PhysicalSwitch::query()
            ->whereIn('id', array_values(array_unique($switchIds)))
            ->get(['id', 'name', 'identifier'])
            ->mapWithKeys(fn (PhysicalSwitch $switch): array => [
                'switch:'.$switch->id => $switch->name.' ('.$switch->identifier.')',
            ])
            ->all();

        $sensorMap = Sensor::query()
            ->whereIn('id', array_values(array_unique($sensorIds)))
            ->get(['id', 'name', 'identifier'])
            ->mapWithKeys(fn (Sensor $sensor): array => [
                'sensor:'.$sensor->id => $sensor->name.' ('.$sensor->identifier.')',
            ])
            ->all();

        return view('admin.hardware.bindings.index', [
            'bindings' => $bindings,
            'accessSources' => Source::query()->orderBy('name')->get(['id', 'name', 'type']),
            'actionOptions' => AccessBindingActionKey::options(),
            'selectedActionKey' => $selectedAction?->value,
            'targetLabels' => array_merge($readerMap, $lockMap, $areaMap, $switchMap, $sensorMap),
            'readerTargets' => Reader::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'lockTargets' => Lock::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'areaTargets' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'switchTargets' => PhysicalSwitch::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'sensorTargets' => Sensor::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.hardware.bindings.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $binding = AdapterBinding::create($validated);

        if ($binding->target_type === 'sensor') {
            $sensor = Sensor::query()->find($binding->target_id);
            if ($sensor instanceof Sensor) {
                app(AccessControlMqttPublisher::class)->publishSensorState($sensor, ['force' => true]);
            }
        }

        return redirect()->route('admin.access-bindings.index')->with('status', 'Binding created successfully.');
    }

    public function edit(AdapterBinding $binding): View
    {
        return view('admin.hardware.bindings.edit', $this->formData($binding));
    }

    public function update(Request $request, AdapterBinding $binding): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $binding->update($validated);

        if ($binding->target_type === 'sensor') {
            $sensor = Sensor::query()->find($binding->target_id);
            if ($sensor instanceof Sensor) {
                app(AccessControlMqttPublisher::class)->publishSensorState($sensor, ['force' => true]);
            }
        }

        return redirect()->route('admin.access-bindings.index')->with('status', 'Binding updated successfully.');
    }

    public function destroy(AdapterBinding $binding): RedirectResponse
    {
        $binding->delete();

        return redirect()->route('admin.access-bindings.index')->with('status', 'Binding deleted successfully.');
    }

    /**
     * @return array{direction:string,adapter_type:string,target_type:string,target_id:int,source_id:?int,action_key:int,channel:?string,signal_reversed:bool,enabled:bool,config:array<string,mixed>,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:input,output'],
            'adapter_type' => ['required', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'target_type' => ['required', 'string', 'in:reader,lock,area,switch,sensor'],
            'target_id' => ['required', 'integer', 'min:1'],
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'action_key' => ['required'],
            'channel' => ['nullable', 'string', 'max:255'],
            'signal_reversed' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'config_json' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'string'],
            'mqtt_periodic_updates_enabled' => ['nullable', 'string', 'in:inherit,0,1'],
            'mqtt_periodic_update_frequency_seconds' => ['nullable', 'integer', 'min:1'],
        ]);

        $resolvedAction = AccessBindingActionKey::fromStored($validated['action_key']);
        if (! $resolvedAction instanceof AccessBindingActionKey) {
            throw ValidationException::withMessages([
                'action_key' => 'Selected action key is invalid.',
            ]);
        }

        if ($validated['direction'] === 'input' && ! $resolvedAction->isInputAction()) {
            throw ValidationException::withMessages([
                'action_key' => 'Selected action key is not valid for input bindings.',
            ]);
        }

        if ($validated['direction'] === 'output' && ! $resolvedAction->isOutputAction()) {
            throw ValidationException::withMessages([
                'action_key' => 'Selected action key is not valid for output bindings.',
            ]);
        }

        if ($validated['target_type'] === 'sensor') {
            if ($validated['direction'] !== 'input') {
                throw ValidationException::withMessages([
                    'direction' => 'Sensor bindings must use input direction.',
                ]);
            }

            if ($resolvedAction !== AccessBindingActionKey::SENSOR_STATE) {
                throw ValidationException::withMessages([
                    'action_key' => 'Sensor bindings must use SensorState action key.',
                ]);
            }
        }

        if ($validated['target_type'] !== 'sensor' && $resolvedAction === AccessBindingActionKey::SENSOR_STATE) {
            throw ValidationException::withMessages([
                'action_key' => 'SensorState action key is only valid for sensor targets.',
            ]);
        }

        $this->assertTargetExists($validated['target_type'], (int) $validated['target_id']);

        $config = $this->decodeJsonField($validated['config_json'] ?? null, 'config_json');

        $periodicMode = strtolower(trim((string) ($validated['mqtt_periodic_updates_enabled'] ?? 'inherit')));
        if (in_array($periodicMode, ['0', '1'], true)) {
            $config['mqtt_periodic_updates_enabled'] = $periodicMode === '1';
        } else {
            unset($config['mqtt_periodic_updates_enabled']);
        }

        if (isset($validated['mqtt_periodic_update_frequency_seconds']) && is_numeric($validated['mqtt_periodic_update_frequency_seconds'])) {
            $config['mqtt_periodic_update_frequency_seconds'] = max(1, (int) $validated['mqtt_periodic_update_frequency_seconds']);
        } else {
            unset($config['mqtt_periodic_update_frequency_seconds']);
        }

        return [
            'direction' => $validated['direction'],
            'adapter_type' => $capabilities->normalizeBindingAdapterType($validated['adapter_type']) ?? $validated['adapter_type'],
            'target_type' => $validated['target_type'],
            'target_id' => (int) $validated['target_id'],
            'source_id' => isset($validated['source_id']) ? (int) $validated['source_id'] : null,
            'action_key' => $resolvedAction->value,
            'channel' => $this->nullableString($validated['channel'] ?? null),
            'signal_reversed' => (bool) $validated['signal_reversed'],
            'enabled' => (bool) $validated['enabled'],
            'config' => $config,
            'metadata' => $this->decodeJsonField($validated['metadata_json'] ?? null, 'metadata_json'),
        ];
    }

    private function assertTargetExists(string $targetType, int $targetId): void
    {
        $exists = match ($targetType) {
            'reader' => Reader::query()->whereKey($targetId)->exists(),
            'lock' => Lock::query()->whereKey($targetId)->exists(),
            'area' => Area::query()->whereKey($targetId)->exists(),
            'switch' => PhysicalSwitch::query()->whereKey($targetId)->exists(),
            'sensor' => Sensor::query()->whereKey($targetId)->exists(),
            default => false,
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                'target_id' => 'Selected target does not exist for target type ['.$targetType.'].',
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

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string,mixed>
     */
    private function formData(?AdapterBinding $binding = null): array
    {
        $inputActionOptions = AccessBindingActionKey::options('input');

        return [
            'accessBinding' => $binding,
            'accessSources' => Source::query()->orderBy('name')->get(['id', 'name', 'identifier', 'type']),
            'readerTargets' => Reader::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'lockTargets' => Lock::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'areaTargets' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'switchTargets' => PhysicalSwitch::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'sensorTargets' => Sensor::query()->orderBy('name')->get(['id', 'name', 'identifier']),
            'actionOptions' => AccessBindingActionKey::options(),
            'inputActionOptions' => $inputActionOptions,
            'sensorInputActionOptions' => array_values(array_filter(
                $inputActionOptions,
                static fn (array $option): bool => ($option['value'] ?? null) === AccessBindingActionKey::SENSOR_STATE->value,
            )),
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
        ];
    }
}
