<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Jobs\PublishReaderState;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlCapabilityRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\AccessControl\OutputAdapterRegistry;
use OTGH\AccessControl\Core\Support\SignalValueMapper;
use Throwable;

class AccessLockController extends Controller
{
    public function index(): View
    {
        return view('admin.hardware.locks.index', [
            'accessLocks' => Lock::query()
                ->with(['area.readers'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function show(Lock $lock): View
    {
        $lock->loadMissing(['area.readers']);

        $recentEvents = Event::query()
            ->where(function ($query) use ($lock): void {
                $query->where('access_lock_id', $lock->id)
                    ->orWhere('access_area_id', $lock->area_id);
            })
            ->latest('id')
            ->limit(25)
            ->get();

        return view('admin.hardware.locks.show', [
            'accessLock' => $lock,
            'lockStatus' => $this->resolveLockStatus($lock),
            'autolockSettings' => app(AutolockSettingsResolver::class)->resolveForAreaAndLock($lock->area, $lock),
            'recentEvents' => $recentEvents,
            'primaryReader' => $lock->area?->readers->sortBy('id')->first(),
            'lockBindings' => AdapterBinding::query()
                ->where('direction', 'output')
                ->where('target_type', 'lock')
                ->where('target_id', $lock->id)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function editBindings(Lock $lock): View
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $rows = AdapterBinding::query()
            ->where('direction', 'output')
            ->where('target_type', 'lock')
            ->where('target_id', $lock->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => [
                'source_id' => $binding->source_id,
                'adapter_type' => $capabilities->normalizeBindingAdapterType((string) $binding->adapter_type) ?? (string) $binding->adapter_type,
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

        return view('admin.hardware.locks.bindings', [
            'accessLock' => $lock,
            'bindingRows' => $rows,
            'accessSources' => Source::query()->orderBy('name')->get(['id', 'name', 'type']),
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'outputActionOptions' => AccessBindingActionKey::options('output'),
        ]);
    }

    public function updateBindings(Request $request, Lock $lock): RedirectResponse
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $validated = $request->validate([
            'outputs' => ['sometimes', 'array'],
            'outputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'outputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'outputs.*.action_key' => ['nullable'],
            'outputs.*.channel' => ['nullable', 'string', 'max:255'],
            'outputs.*.signal_reversed' => ['nullable', 'boolean'],
            'outputs.*.enabled' => ['nullable', 'boolean'],
            'outputs.*.config_json' => ['nullable', 'string'],
        ]);

        $rows = $this->normalizeBindingRows((array) ($validated['outputs'] ?? []));
        $this->syncLockBindings($lock, $rows);

        Reader::query()
            ->where('area_id', $lock->area_id)
            ->get()
            ->each(fn (Reader $reader) => PublishReaderState::dispatch($reader));

        return redirect()->route('admin.access-locks.show', $lock)->with('status', 'Lock bindings updated successfully.');
    }

    public function create(): View
    {
        return view('admin.hardware.locks.create', [
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        DB::transaction(function () use ($validated): void {
            $lock = Lock::create($validated);

            if ($lock->is_primary) {
                $this->unsetOtherPrimaryLocks($lock->area_id, $lock->id);
            }
        });

        return redirect()->route('admin.access-locks.index')->with('status', 'Lock created successfully.');
    }

    public function edit(Lock $lock): View
    {
        return view('admin.hardware.locks.edit', [
            'accessLock' => $lock,
            'accessAreas' => Area::query()->orderBy('name')->get(['id', 'name', 'identifier']),
        ]);
    }

    public function update(Request $request, Lock $lock): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $lock);

        DB::transaction(function () use ($lock, $validated): void {
            $lock->update($validated);

            if ($lock->is_primary) {
                $this->unsetOtherPrimaryLocks($lock->area_id, $lock->id);
            }
        });

        return redirect()->route('admin.access-locks.index')->with('status', 'Lock updated successfully.');
    }

    public function destroy(Lock $lock): RedirectResponse
    {
        $lock->delete();

        return redirect()->route('admin.access-locks.index')->with('status', 'Lock deleted successfully.');
    }

    /**
     * @return array{area_id:int,name:string,identifier:string,is_primary:bool,config:array<string,mixed>,metadata:array<string,mixed>}
     */
    private function validateAndNormalize(Request $request, ?Lock $lock = null): array
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('locks', 'identifier')->ignore($lock?->id),
            ],
            'is_primary' => ['required', 'boolean'],
            'override_autolock_enabled' => ['nullable', 'in:inherit,0,1'],
            'override_autolock_duration' => ['nullable', 'integer', 'min:0'],
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

        $config = $this->decodeJsonField($validated['config_json'] ?? null, 'config_json');
        $overrideEnabled = $validated['override_autolock_enabled'] ?? 'inherit';
        $overrideDurationRaw = $validated['override_autolock_duration'] ?? null;

        if ($overrideEnabled === 'inherit') {
            data_forget($config, 'locking.autolock_override_enabled');
        } else {
            data_set($config, 'locking.autolock_override_enabled', (string) $overrideEnabled === '1');
        }

        if ($overrideDurationRaw === null || (is_string($overrideDurationRaw) && trim($overrideDurationRaw) === '')) {
            data_forget($config, 'locking.autolock_override_duration');
        } else {
            data_set($config, 'locking.autolock_override_duration', max(0, (int) $overrideDurationRaw));
        }

        return [
            'area_id' => (int) $validated['area_id'],
            'name' => $validated['name'],
            'identifier' => $identifier,
            'is_primary' => (bool) $validated['is_primary'],
            'config' => $config,
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

    private function unsetOtherPrimaryLocks(int $roomId, int $activeLockId): void
    {
        Lock::query()
            ->where('area_id', $roomId)
            ->where('id', '!=', $activeLockId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeBindingRows(array $rows): array
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
                    "outputs.$index" => 'Adapter Type and Action Key are required when a row is used.',
                ]);
            }

            if (! $resolvedAction->isOutputAction()) {
                throw ValidationException::withMessages([
                    "outputs.$index.action_key" => 'Selected action key is not valid for output bindings.',
                ]);
            }

            $configJson = trim((string) ($row['config_json'] ?? ''));
            $config = [];
            if ($configJson !== '') {
                $decoded = json_decode($configJson, true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        "outputs.$index.config_json" => 'Must be valid JSON object syntax.',
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
                'channel' => $channel === '' ? null : $channel,
                'signal_reversed' => (bool) ($row['signal_reversed'] ?? false),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'config' => $config,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function syncLockBindings(Lock $lock, array $rows): void
    {
        $existing = AdapterBinding::query()
            ->where('direction', 'output')
            ->where('target_type', 'lock')
            ->where('target_id', $lock->id)
            ->get();

        DB::transaction(function () use ($existing, $lock, $rows): void {
            $activeIds = [];

            foreach ($rows as $row) {
                $binding = AdapterBinding::withTrashed()
                    ->where('direction', 'output')
                    ->where('target_type', 'lock')
                    ->where('target_id', $lock->id)
                    ->where('adapter_type', $row['adapter_type'])
                    ->where('action_key', $row['action_key'])
                    ->where('channel', $row['channel'])
                    ->latest('id')
                    ->first();

                if (! $binding instanceof AdapterBinding) {
                    $binding = new AdapterBinding;
                    $binding->direction = 'output';
                    $binding->target_type = 'lock';
                    $binding->target_id = $lock->id;
                    $binding->adapter_type = $row['adapter_type'];
                    $binding->action_key = $row['action_key'];
                    $binding->channel = $row['channel'];
                }

                if ($binding->trashed()) {
                    $binding->restore();
                }

                $binding->source_id = $row['source_id'];
                $binding->signal_reversed = (bool) $row['signal_reversed'];
                $binding->enabled = (bool) $row['enabled'];
                $binding->config = is_array($row['config']) ? $row['config'] : [];
                $binding->metadata = [
                    'managed_by' => 'admin_lock_bindings_form',
                    'updated_at' => now()->toIso8601String(),
                ];
                $binding->save();

                $activeIds[] = $binding->id;
            }

            foreach ($existing as $binding) {
                if (! in_array($binding->id, $activeIds, true)) {
                    $binding->delete();
                }
            }
        });
    }

    /**
     * @return array{state:string,label:string,badge:string,channel:?string,adapter_type:?string,signal_reversed:bool,error:?string}
     */
    private function resolveLockStatus(Lock $lock): array
    {
        $binding = AdapterBinding::query()
            ->where('direction', 'output')
            ->where('target_type', 'lock')
            ->where('target_id', $lock->id)
            ->where('enabled', true)
            ->whereIn('action_key', AccessBindingActionKey::LOCK_POWER->queryCandidates())
            ->orderByDesc('id')
            ->first();

        if (! $binding instanceof AdapterBinding) {
            return [
                'state' => 'unknown',
                'label' => 'Unknown',
                'badge' => 'secondary',
                'channel' => null,
                'adapter_type' => null,
                'signal_reversed' => false,
                'error' => 'No enabled LOCK_POWER binding found for this lock.',
            ];
        }

        try {
            $adapter = app(OutputAdapterRegistry::class)->resolve((string) $binding->adapter_type);
            $sourceConfig = is_array($binding->source?->config) ? $binding->source->config : [];
            $bindingConfig = is_array($binding->config) ? $binding->config : [];
            $adapterConfig = array_replace_recursive($sourceConfig, $bindingConfig);

            $raw = $adapter->read($binding->channel, $adapterConfig);
            $canonicalBool = SignalValueMapper::toCanonicalBool($raw, (bool) $binding->signal_reversed);
            $canonical = $canonicalBool === null ? null : ($canonicalBool ? 1 : 0);

            $state = match ($canonical) {
                1 => 'locked',
                0 => 'unlocked',
                default => 'unknown',
            };

            return [
                'state' => $state,
                'label' => ucfirst($state),
                'badge' => match ($state) {
                    'locked' => 'danger',
                    'unlocked' => 'success',
                    default => 'secondary',
                },
                'channel' => $binding->channel,
                'adapter_type' => $binding->adapter_type,
                'signal_reversed' => (bool) $binding->signal_reversed,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'state' => 'unknown',
                'label' => 'Unknown',
                'badge' => 'secondary',
                'channel' => $binding->channel,
                'adapter_type' => $binding->adapter_type,
                'signal_reversed' => (bool) $binding->signal_reversed,
                'error' => $e->getMessage(),
            ];
        }
    }
}
