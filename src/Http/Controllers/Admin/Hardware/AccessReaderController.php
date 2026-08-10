<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Hardware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Jobs\PublishReaderState;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlCapabilityRegistry;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;
use OTGH\AccessControl\Core\Services\AccessControl\AutolockSettingsResolver;
use OTGH\AccessControl\Core\Services\AccessControl\LockStatusResolver;

class AccessReaderController extends Controller
{
    public function __construct(private readonly AccessControlSettingsRepository $settings) {}

    public function index(): View
    {
        return view('admin.hardware.readers.index');
    }

    public function show(Reader $reader): View
    {
        $recentEvents = Event::with(['accessUser', 'accessCard'])
            ->where('origin_type', 'reader')
            ->where('origin_id', $reader->id)
            ->latest('id')
            ->limit(25)
            ->get();

        $readerBindings = AdapterBinding::query()
            ->where('target_type', 'reader')
            ->where('target_id', $reader->id)
            ->orderBy('action_key')
            ->get();

        return view('admin.hardware.readers.show', [
            'accessReader' => $reader,
            'recentEvents' => $recentEvents,
            'readerBindings' => $readerBindings,
        ]);
    }

    public function create(): View
    {
        $defaultOutputAdapterType = $this->defaultOutputAdapterType();

        return view('admin.hardware.readers.create', [
            'accessAreas' => Area::query()->orderBy('name')->get(),
            'accessSources' => Source::query()->orderBy('name')->get(),
            'inputBindings' => [],
            'outputBindings' => $defaultOutputAdapterType === '' ? [] : [[
                'source_id' => null,
                'adapter_type' => $defaultOutputAdapterType,
                'action_key' => AccessBindingActionKey::READER_FEEDBACK_STATE->value,
                'channel' => '',
                'signal_reversed' => false,
                'enabled' => true,
                'config_json' => '{}',
            ]],
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'inputActionOptions' => AccessBindingActionKey::options('input'),
            'outputActionOptions' => AccessBindingActionKey::options('output'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $inputBindings = $validated['input_bindings'];
        $outputBindings = $validated['output_bindings'];
        unset($validated['input_bindings'], $validated['output_bindings']);

        $reader = Reader::create($validated);

        $this->syncBindings($reader->fresh(), $inputBindings, $outputBindings);

        PublishReaderState::dispatch($reader);

        return redirect()->route('admin.access-readers.index')->with('status', 'Access reader created successfully.');
    }

    public function edit(Reader $reader): View
    {
        $inputBindings = AdapterBinding::query()
            ->where('direction', 'input')
            ->where('target_type', 'reader')
            ->where('target_id', $reader->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => $this->bindingModelToFormRow($binding))
            ->values()
            ->all();

        $outputBindings = AdapterBinding::query()
            ->where('direction', 'output')
            ->where('target_type', 'reader')
            ->where('target_id', $reader->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => $this->bindingModelToFormRow($binding))
            ->values()
            ->all();

        return view('admin.hardware.readers.edit', [
            'accessReader' => $reader,
            'accessAreas' => Area::query()->orderBy('name')->get(),
            'accessSources' => Source::query()->orderBy('name')->get(),
            'inputBindings' => $inputBindings,
            'outputBindings' => $outputBindings,
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'inputActionOptions' => AccessBindingActionKey::options('input'),
            'outputActionOptions' => AccessBindingActionKey::options('output'),
        ]);
    }

    public function update(Request $request, Reader $reader): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $reader);
        $previousName = $reader->name;
        $previousConfig = is_array($reader->config) ? $reader->config : [];
        $previousRoomId = $reader->area_id;

        $inputBindings = $validated['input_bindings'];
        $outputBindings = $validated['output_bindings'];
        unset($validated['input_bindings'], $validated['output_bindings']);

        $reader->update($validated);

        $bindingsChanged = $this->syncBindings($reader->fresh(), $inputBindings, $outputBindings);

        if ($bindingsChanged || $this->hasMqttStateRelevantChanges($previousName, $previousConfig, $previousRoomId, $validated)) {
            PublishReaderState::dispatch($reader->fresh());
        }

        return redirect()->route('admin.access-readers.index')->with('status', 'Access reader updated successfully.');
    }

    public function editBindings(Reader $reader): View
    {
        $inputBindings = AdapterBinding::query()
            ->where('direction', 'input')
            ->where('target_type', 'reader')
            ->where('target_id', $reader->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => $this->bindingModelToFormRow($binding))
            ->values()
            ->all();

        $outputBindings = AdapterBinding::query()
            ->where('direction', 'output')
            ->where('target_type', 'reader')
            ->where('target_id', $reader->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AdapterBinding $binding): array => $this->bindingModelToFormRow($binding))
            ->values()
            ->all();

        return view('admin.hardware.readers.bindings', [
            'accessReader' => $reader,
            'accessSources' => Source::query()->orderBy('name')->get(),
            'inputBindings' => $inputBindings,
            'outputBindings' => $outputBindings,
            'adapterTypeOptions' => app(AccessControlCapabilityRegistry::class)->bindingAdapterOptions(),
            'inputActionOptions' => AccessBindingActionKey::options('input'),
            'outputActionOptions' => AccessBindingActionKey::options('output'),
        ]);
    }

    public function updateBindings(Request $request, Reader $reader): RedirectResponse
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $validated = $request->validate([
            'inputs' => ['sometimes', 'array'],
            'inputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'inputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'inputs.*.action_key' => ['nullable'],
            'inputs.*.channel' => ['nullable', 'string', 'max:255'],
            'inputs.*.signal_reversed' => ['nullable', 'boolean'],
            'inputs.*.enabled' => ['nullable', 'boolean'],
            'inputs.*.config_json' => ['nullable', 'string'],
            'outputs' => ['sometimes', 'array'],
            'outputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'outputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'outputs.*.action_key' => ['nullable'],
            'outputs.*.channel' => ['nullable', 'string', 'max:255'],
            'outputs.*.signal_reversed' => ['nullable', 'boolean'],
            'outputs.*.enabled' => ['nullable', 'boolean'],
            'outputs.*.config_json' => ['nullable', 'string'],
        ]);

        $inputBindings = $this->normalizeBindingRows((array) ($validated['inputs'] ?? []), 'input');
        $outputBindings = $this->normalizeBindingRows((array) ($validated['outputs'] ?? []), 'output');

        $changed = $this->syncBindings($reader->fresh(), $inputBindings, $outputBindings);

        if ($changed) {
            PublishReaderState::dispatch($reader->fresh());
        }

        return redirect()->route('admin.access-readers.bindings.edit', $reader)
            ->with('status', 'Reader bindings updated successfully.');
    }

    public function destroy(Reader $reader): RedirectResponse
    {
        $reader->delete();

        return redirect()->route('admin.access-readers.index')->with('status', 'Access reader deleted successfully.');
    }

    public function toggleLock(Request $request, Reader $reader): RedirectResponse|JsonResponse
    {
        $status = $this->resolveSingleLockStatus($reader);

        if (($status['state'] ?? 'unknown') === 'unknown') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Unable to toggle lock: current lock state is unavailable.',
                ], 422);
            }

            return redirect()->route('admin.access-readers.index')->with('status', 'Unable to toggle lock: current lock state is unavailable.');
        }

        $isCurrentlyLocked = ($status['state'] ?? null) === 'locked';
        $targetValue = $isCurrentlyLocked ? 0 : 1;
        $allowAutoRelock = $targetValue === 0;
        $area = $reader->area;
        $primaryLock = $area?->primaryLock();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area?->id,
            'access_lock_id' => $primaryLock?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'lock',
            'origin_id' => $primaryLock?->id ?? $reader->id,
            'origin_label' => $primaryLock?->name ?? $reader->name,
            'granted' => true,
            'status' => $targetValue === 1 ? 'admin_lock_requested' : 'admin_unlock_requested',
            'reason' => $targetValue === 1
                ? 'Lock requested via admin readers list.'
                : 'Unlock requested via admin readers list.',
            'metadata' => [
                'source' => 'admin',
                'event' => $targetValue === 1 ? 'lock_requested' : 'unlock_requested',
                'allow_auto_relock' => $allowAutoRelock,
            ],
            'ip_address' => $request->ip(),
        ]);

        ProcessReaderEvent::dispatch(null, $reader, $targetValue, $allowAutoRelock, 'admin');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $targetValue === 1 ? 'Lock command queued.' : 'Unlock command queued.',
                'target_value' => $targetValue,
            ], 202);
        }

        return redirect()->route('admin.access-readers.index')->with(
            'status',
            $targetValue === 1 ? 'Lock command queued.' : 'Unlock command queued.'
        );
    }

    public function toggleAutolock(Request $request, Reader $reader): RedirectResponse|JsonResponse
    {
        $area = $reader->area;

        if (! $area) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Reader must be assigned to an area before toggling auto-lock defaults.',
                ], 422);
            }

            return redirect()->route('admin.access-readers.index')->with('status', 'Reader must be assigned to an area before toggling auto-lock defaults.');
        }

        $current = (bool) data_get($area->config, 'locking.autolock_enabled', false);
        $updated = ! $current;
        $duration = max(0, (int) data_get($area->config, 'locking.autolock_duration', 0));

        $config = is_array($area->config) ? $area->config : [];
        data_set($config, 'locking.autolock_enabled', $updated);
        data_set($config, 'locking.autolock_duration', $duration);

        $area->config = $config;
        $area->save();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area->id,
            'access_lock_id' => $area->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'area',
            'origin_id' => $area->id,
            'origin_label' => $area->name,
            'granted' => true,
            'status' => 'admin_autolock_updated',
            'reason' => $updated
                ? 'Auto-lock enabled via admin readers list.'
                : 'Auto-lock disabled via admin readers list.',
            'metadata' => [
                'source' => 'admin',
                'event' => 'autolock_updated',
                'autolock_enabled' => $updated,
                'autolock_duration' => $duration,
                'autolock_scope' => 'area_default',
                'area_id' => $area->id,
            ],
            'ip_address' => $request->ip(),
        ]);

        Reader::query()
            ->where('area_id', $area->id)
            ->get()
            ->each(fn (Reader $reader) => PublishReaderState::dispatch($reader->fresh()));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $updated ? 'Auto-lock enabled.' : 'Auto-lock disabled.',
                'autolock_enabled' => $updated,
                'autolock_duration' => $duration,
            ]);
        }

        return redirect()->route('admin.access-readers.index')->with(
            'status',
            $updated ? 'Auto-lock enabled.' : 'Auto-lock disabled.'
        );
    }

    public function status(Reader $reader): JsonResponse
    {
        $reader->loadMissing('area.locks');
        $status = $this->resolveSingleLockStatus($reader);
        $autolock = app(AutolockSettingsResolver::class)->resolveForReader($reader);

        return response()->json([
            'reader_id' => $reader->id,
            'lock' => [
                'state' => $status['state'] ?? 'unknown',
                'label' => $status['label'] ?? 'Unknown',
                'badge' => $status['badge'] ?? 'secondary',
                'error' => $status['error'] ?? null,
            ],
            'autolock' => [
                'enabled' => (bool) ($autolock['enabled'] ?? false),
                'duration' => max(0, (int) ($autolock['duration'] ?? 0)),
                'source' => (string) ($autolock['source'] ?? 'area_default'),
            ],
        ]);
    }

    /**
     * @return array{name:string,identifier:string,area_id:?int,config:array<string,mixed>,metadata:array<string,mixed>,input_bindings:array<int,array<string,mixed>>,output_bindings:array<int,array<string,mixed>>}
     */
    private function validateAndNormalize(Request $request, ?Reader $reader = null): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique('readers', 'identifier')->ignore($reader?->id),
            ],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'general_feedback_state_duration' => ['required', 'integer', 'min:0'],
            'general_reader_mode' => ['nullable', 'string', 'in:card_only,keypad'],
            'general_input_format' => ['nullable', 'string', 'in:wiegand'],
            'wiegand_device' => ['nullable', 'string', 'max:255'],
            'wiegand_baud_rate' => ['nullable', 'integer', 'min:1'],
            'wiegand_timeout' => ['nullable', 'numeric', 'min:0.1'],
            'wiegand_duplicate_window' => ['nullable', 'numeric', 'min:0'],
            'wiegand_doorbell_duplicate_window' => ['nullable', 'numeric', 'min:0'],
            'wiegand_keypad_timeout' => ['nullable', 'numeric', 'min:0.1'],
            'wiegand_card_min_value' => ['nullable', 'integer', 'min:0'],
            'wiegand_doorbell_value' => ['nullable', 'integer', 'min:0'],

            'inputs' => ['sometimes', 'array'],
            'inputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'inputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'inputs.*.action_key' => ['nullable'],
            'inputs.*.channel' => ['nullable', 'string', 'max:255'],
            'inputs.*.signal_reversed' => ['nullable', 'boolean'],
            'inputs.*.enabled' => ['nullable', 'boolean'],
            'inputs.*.config_json' => ['nullable', 'string'],

            'outputs' => ['sometimes', 'array'],
            'outputs.*.target' => ['nullable', 'string', 'in:reader'],
            'outputs.*.source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'outputs.*.adapter_type' => ['nullable', 'string', Rule::in($capabilities->bindingAdapterValidationValues())],
            'outputs.*.action_key' => ['nullable'],
            'outputs.*.channel' => ['nullable', 'string', 'max:255'],
            'outputs.*.signal_reversed' => ['nullable', 'boolean'],
            'outputs.*.enabled' => ['nullable', 'boolean'],
            'outputs.*.config_json' => ['nullable', 'string'],

            'metadata_reader_model' => ['nullable', 'string', 'max:255'],
            'metadata_reader_type' => ['nullable', 'string', 'max:255'],
            'metadata_lock_model' => ['nullable', 'string', 'max:255'],
            'metadata_lock_type' => ['nullable', 'string', 'max:255'],
        ]);

        $normalizedInputs = $this->normalizeBindingRows((array) ($validated['inputs'] ?? []), 'input');
        $normalizedOutputs = $this->normalizeBindingRows((array) ($validated['outputs'] ?? []), 'output');

        return [
            'name' => $validated['name'],
            'identifier' => $validated['identifier'],
            'area_id' => (int) $validated['area_id'],
            'config' => [
                'general' => [
                    'feedback_state_duration' => (int) $validated['general_feedback_state_duration'],
                    'reader_mode' => $validated['general_reader_mode'] ?? 'card_only',
                    'input_format' => $validated['general_input_format'] ?? 'wiegand',
                ],
                'wiegand' => [
                    'device' => $this->nullableString($validated['wiegand_device'] ?? null),
                    'baud_rate' => (int) ($validated['wiegand_baud_rate'] ?? $this->settings->getInt('wiegand.default_baud_rate', 9600)),
                    'timeout' => (float) ($validated['wiegand_timeout'] ?? $this->settings->get('wiegand.default_timeout_seconds', 1.0)),
                    'duplicate_window' => (float) ($validated['wiegand_duplicate_window'] ?? $this->settings->get('wiegand.default_duplicate_window_seconds', 2.0)),
                    'doorbell_duplicate_window' => (float) ($validated['wiegand_doorbell_duplicate_window'] ?? $this->settings->get('wiegand.default_doorbell_duplicate_window_seconds', 2.0)),
                    'keypad_timeout' => (float) ($validated['wiegand_keypad_timeout'] ?? $this->settings->get('wiegand.default_keypad_timeout_seconds', 3.0)),
                    'card_min_value' => (int) ($validated['wiegand_card_min_value'] ?? $this->settings->getInt('wiegand.default_card_min_value', 15)),
                    'doorbell_value' => (int) ($validated['wiegand_doorbell_value'] ?? $this->settings->getInt('wiegand.default_doorbell_value', 11)),
                ],
            ],
            'metadata' => [
                'reader' => [
                    'model' => $this->nullableString($validated['metadata_reader_model'] ?? null),
                    'type' => $this->nullableString($validated['metadata_reader_type'] ?? null),
                ],
                'lock' => [
                    'model' => $this->nullableString($validated['metadata_lock_model'] ?? null),
                    'type' => $this->nullableString($validated['metadata_lock_type'] ?? null),
                ],
            ],
            'input_bindings' => $normalizedInputs,
            'output_bindings' => $normalizedOutputs,
        ];
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
     * @param  array<string,mixed>  $previousConfig
     * @param  array{name:string,identifier:string,area_id:?int,config:array<string,mixed>,metadata:array<string,mixed>}  $validated
     */
    private function hasMqttStateRelevantChanges(string $previousName, array $previousConfig, ?int $previousRoomId, array $validated): bool
    {
        if (((int) ($validated['area_id'] ?? 0)) !== ((int) ($previousRoomId ?? 0))) {
            return true;
        }

        return trim($previousName) !== trim($validated['name']);
    }

    /**
     * @param  array<int,array<string,mixed>>  $inputBindings
     * @param  array<int,array<string,mixed>>  $outputBindings
     */
    private function syncBindings(Reader $reader, array $inputBindings, array $outputBindings): bool
    {
        if (! Schema::hasTable('adapter_bindings')) {
            return false;
        }

        $reader->refresh();

        $desiredRows = [];

        foreach ($inputBindings as $bindingRow) {
            $desiredRows[] = [
                'direction' => 'input',
                'target_type' => 'reader',
                'target_id' => (int) $reader->id,
                'source_id' => $bindingRow['source_id'],
                'adapter_type' => $bindingRow['adapter_type'],
                'action_key' => $bindingRow['action_key'],
                'channel' => $bindingRow['channel'],
                'signal_reversed' => $bindingRow['signal_reversed'],
                'enabled' => $bindingRow['enabled'],
                'config' => $bindingRow['config'],
            ];
        }

        foreach ($outputBindings as $bindingRow) {
            $desiredRows[] = [
                'direction' => 'output',
                'target_type' => 'reader',
                'target_id' => (int) $reader->id,
                'source_id' => $bindingRow['source_id'],
                'adapter_type' => $bindingRow['adapter_type'],
                'action_key' => $bindingRow['action_key'],
                'channel' => $bindingRow['channel'],
                'signal_reversed' => $bindingRow['signal_reversed'],
                'enabled' => $bindingRow['enabled'],
                'config' => $bindingRow['config'],
            ];
        }

        $existingBindings = AdapterBinding::query()
            ->where(function ($query) use ($reader): void {
                $query->where('target_type', 'reader')->where('target_id', $reader->id);
            })
            ->get();

        $beforeSignature = $existingBindings
            ->map(fn (AdapterBinding $binding): string => $this->bindingSignature([
                'direction' => (string) $binding->direction,
                'target_type' => (string) $binding->target_type,
                'target_id' => (int) $binding->target_id,
                'source_id' => $binding->source_id === null ? null : (int) $binding->source_id,
                'adapter_type' => (string) $binding->adapter_type,
                'action_key' => (string) $binding->action_key,
                'channel' => $this->nullableString($binding->channel),
                'signal_reversed' => (bool) $binding->signal_reversed,
                'enabled' => (bool) $binding->enabled,
                'config' => is_array($binding->config) ? $binding->config : [],
            ]))
            ->sort()
            ->values()
            ->all();

        DB::transaction(function () use ($existingBindings, $desiredRows): void {
            $activeIds = [];

            foreach ($desiredRows as $row) {
                $resolvedAction = AccessBindingActionKey::fromStored($row['action_key']);
                $actionCandidates = $resolvedAction instanceof AccessBindingActionKey
                    ? $resolvedAction->queryCandidates()
                    : [(string) $row['action_key']];

                $binding = AdapterBinding::withTrashed()
                    ->where('direction', $row['direction'])
                    ->where('target_type', $row['target_type'])
                    ->where('target_id', $row['target_id'])
                    ->where('adapter_type', $row['adapter_type'])
                    ->whereIn('action_key', $actionCandidates)
                    ->where('channel', $row['channel'])
                    ->latest('id')
                    ->first();

                if ($binding === null) {
                    $binding = new AdapterBinding;
                    $binding->direction = $row['direction'];
                    $binding->target_type = $row['target_type'];
                    $binding->target_id = $row['target_id'];
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
                    'managed_by' => 'admin_reader_bindings_form',
                    'updated_at' => now()->toIso8601String(),
                ];
                $binding->save();

                $activeIds[] = $binding->id;
            }

            foreach ($existingBindings as $existing) {
                if (! in_array($existing->id, $activeIds, true)) {
                    $existing->delete();
                }
            }
        });

        $afterBindings = AdapterBinding::query()
            ->where(function ($query) use ($reader): void {
                $query->where('target_type', 'reader')->where('target_id', $reader->id);
            })
            ->get();

        $afterSignature = $afterBindings
            ->map(fn (AdapterBinding $binding): string => $this->bindingSignature([
                'direction' => (string) $binding->direction,
                'target_type' => (string) $binding->target_type,
                'target_id' => (int) $binding->target_id,
                'source_id' => $binding->source_id === null ? null : (int) $binding->source_id,
                'adapter_type' => (string) $binding->adapter_type,
                'action_key' => (string) $binding->action_key,
                'channel' => $this->nullableString($binding->channel),
                'signal_reversed' => (bool) $binding->signal_reversed,
                'enabled' => (bool) $binding->enabled,
                'config' => is_array($binding->config) ? $binding->config : [],
            ]))
            ->sort()
            ->values()
            ->all();

        return $beforeSignature !== $afterSignature;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeBindingRows(array $rows, string $direction): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);
        $normalized = [];

        foreach ($rows as $rowIndex => $row) {
            $adapterTypeRaw = strtolower(trim((string) ($row['adapter_type'] ?? '')));
            $adapterType = $capabilities->normalizeBindingAdapterType($adapterTypeRaw) ?? $adapterTypeRaw;
            $resolvedAction = AccessBindingActionKey::fromStored($row['action_key'] ?? null);
            $actionKey = $resolvedAction?->value;
            $channel = $this->nullableString($row['channel'] ?? null);
            $sourceId = isset($row['source_id']) && $row['source_id'] !== '' ? (int) $row['source_id'] : null;

            $isEmpty = $adapterType === '' && $actionKey === null && $channel === null && $sourceId === null;
            if ($isEmpty) {
                continue;
            }

            if ($adapterType === '' || $actionKey === null) {
                throw ValidationException::withMessages([
                    $direction === 'input' ? "inputs.$rowIndex" : "outputs.$rowIndex" => 'Adapter Type and Action Key are required when a binding row is used.',
                ]);
            }

            if ($direction === 'input' && ! $resolvedAction->isInputAction()) {
                throw ValidationException::withMessages([
                    "inputs.$rowIndex.action_key" => 'Selected action key is not valid for input bindings.',
                ]);
            }

            if ($direction === 'output' && ! $resolvedAction->isOutputAction()) {
                throw ValidationException::withMessages([
                    "outputs.$rowIndex.action_key" => 'Selected action key is not valid for output bindings.',
                ]);
            }

            $configJson = trim((string) ($row['config_json'] ?? ''));
            $config = [];
            if ($configJson !== '') {
                $decoded = json_decode($configJson, true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        $direction === 'input' ? "inputs.$rowIndex.config_json" : "outputs.$rowIndex.config_json" => 'Must be valid JSON object syntax.',
                    ]);
                }
                $config = $decoded;
            }

            $normalized[] = [
                'target' => 'reader',
                'source_id' => $sourceId,
                'adapter_type' => $adapterType,
                'action_key' => $actionKey,
                'channel' => $channel,
                'signal_reversed' => (bool) ($row['signal_reversed'] ?? false),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'config' => $config,
                'config_json' => $configJson === '' ? '{}' : $configJson,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $binding
     */
    private function bindingSignature(array $binding): string
    {
        ksort($binding);

        return (string) json_encode($binding, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string,mixed>
     */
    private function bindingModelToFormRow(AdapterBinding $binding): array
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);
        $config = is_array($binding->config) ? $binding->config : [];

        return [
            'target' => 'reader',
            'source_id' => $binding->source_id,
            'adapter_type' => $capabilities->normalizeBindingAdapterType((string) $binding->adapter_type) ?? (string) $binding->adapter_type,
            'action_key' => AccessBindingActionKey::fromStored($binding->action_key)?->value,
            'channel' => $binding->channel,
            'signal_reversed' => (bool) $binding->signal_reversed,
            'enabled' => (bool) $binding->enabled,
            'config_json' => (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function defaultOutputAdapterType(): string
    {
        $capabilities = app(AccessControlCapabilityRegistry::class);
        $options = $capabilities->bindingAdapterOptions();

        foreach ($options as $option) {
            if (($option['value'] ?? '') === 'edgelink') {
                return 'edgelink';
            }
        }

        return (string) ($options[0]['value'] ?? '');
    }

    /**
     * @return array{state:string,label:string,badge:string,tag:?string,error:?string}
     */
    private function resolveSingleLockStatus(Reader $reader): array
    {
        return app(LockStatusResolver::class)->resolve($reader);
    }
}
