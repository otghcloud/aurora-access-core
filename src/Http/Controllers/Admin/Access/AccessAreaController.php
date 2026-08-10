<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Access;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Jobs\PublishReaderState;
use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Access\Individual;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\PhysicalSwitch;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\LockStatusResolver;

class AccessAreaController extends Controller
{
    public function index(): View
    {
        /** @var LengthAwarePaginator $areas */
        $areas = Area::query()
            ->with(['readers', 'locks', 'switches'])
            ->withCount(['readers', 'locks', 'switches', 'permissions'])
            ->latest('id')
            ->paginate(20);

        /** @var Collection<int, Area> $items */
        $items = $areas->getCollection();

        $areas->setCollection(
            $items->map(function (Area $area): Area {
                $primaryReader = $this->resolveAreaPrimaryReader($area);
                $area->setAttribute('primary_reader', $primaryReader);
                $area->setAttribute('primary_lock', $area->primaryLock());

                $status = [
                    'state' => 'unknown',
                    'label' => 'Unknown',
                    'badge' => 'secondary',
                    'error' => null,
                ];

                if ($primaryReader !== null) {
                    $resolved = app(LockStatusResolver::class)->resolve($primaryReader);
                    $status = [
                        'state' => $resolved['state'] ?? 'unknown',
                        'label' => $resolved['label'] ?? 'Unknown',
                        'badge' => $resolved['badge'] ?? 'secondary',
                        'error' => $resolved['error'] ?? null,
                    ];
                }

                $area->setAttribute('control', [
                    'lock' => $status,
                    'autolock_enabled' => $area->autolockEnabled(),
                    'autolock_duration' => $area->autolockDuration(),
                ]);

                return $area;
            })
        );

        return view('admin.access.areas.index', [
            'accessAreas' => $areas,
        ]);
    }

    public function lock(Request $request, Area $area): RedirectResponse
    {
        return $this->dispatchAreaLockCommand($request, $area, 1, false, 'Area locked.');
    }

    public function unlock(Request $request, Area $area): RedirectResponse
    {
        return $this->dispatchAreaLockCommand($request, $area, 0, true, 'Area unlocked.');
    }

    public function updateAutolock(Request $request, Area $area): RedirectResponse
    {
        $validated = $request->validate([
            'autolock_enabled' => ['required', 'boolean'],
            'autolock_duration' => ['required', 'integer', 'min:0'],
        ]);

        $enabled = (bool) $validated['autolock_enabled'];
        $duration = max(0, (int) $validated['autolock_duration']);
        $areaConfig = is_array($area->config) ? $area->config : [];
        data_set($areaConfig, 'locking.autolock_enabled', $enabled);
        data_set($areaConfig, 'locking.autolock_duration', $duration);

        $area->config = $areaConfig;
        $area->save();

        $readers = $area->readers()->orderBy('id')->get();
        foreach ($readers as $reader) {
            PublishReaderState::dispatch($reader->fresh());
        }

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
            'reason' => 'Area auto-lock settings changed via areas overview.',
            'metadata' => [
                'source' => 'admin',
                'event' => 'area_autolock_update',
                'area_id' => $area->id,
                'area_identifier' => $area->identifier,
                'autolock_enabled' => $enabled,
                'autolock_duration' => $duration,
                'reader_ids' => $readers->pluck('id')->values()->all(),
                'autolock_scope' => 'area_default',
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.access-areas.index')->with('status', $enabled ? 'Area auto-lock enabled.' : 'Area auto-lock disabled.');
    }

    public function create(): View
    {
        return view('admin.access.areas.create', $this->formData());
    }

    public function bindings(Area $area): View
    {
        $area->load(['readers', 'locks']);

        $readerIds = $area->readers->pluck('id')->all();
        $lockIds = $area->locks->pluck('id')->all();

        $bindings = AdapterBinding::query()
            ->with('source')
            ->where(function ($query) use ($readerIds, $lockIds): void {
                if ($readerIds !== []) {
                    $query->orWhere(function ($inner) use ($readerIds): void {
                        $inner->where('target_type', 'reader')->whereIn('target_id', $readerIds);
                    });
                }

                if ($lockIds !== []) {
                    $query->orWhere(function ($inner) use ($lockIds): void {
                        $inner->where('target_type', 'lock')->whereIn('target_id', $lockIds);
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.access.areas.bindings', [
            'accessArea' => $area,
            'bindings' => $bindings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request);

        $readerIds = $validated['reader_ids'];
        $lockIds = $validated['lock_ids'];
        $switchIds = $validated['switch_ids'];
        $permissions = $validated['permissions'];
        unset($validated['reader_ids'], $validated['lock_ids'], $validated['switch_ids'], $validated['permissions']);

        DB::transaction(function () use ($validated, $readerIds, $lockIds, $switchIds, $permissions): void {
            $area = Area::create($validated);

            $this->syncReaderAssignments($area, $readerIds);
            $this->assignLocksToArea($area, $lockIds);
            $this->assignSwitchesToArea($area, $switchIds);
            $this->syncAreaPermissions($area, $permissions);
        });

        return redirect()->route('admin.access-areas.index')->with('status', 'Area created successfully.');
    }

    public function edit(Area $area): View
    {
        return view('admin.access.areas.edit', [
            'accessArea' => $area,
            ...$this->formData($area),
        ]);
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $validated = $this->validateAndNormalize($request, $area);

        $readerIds = $validated['reader_ids'];
        $lockIds = $validated['lock_ids'];
        $switchIds = $validated['switch_ids'];
        $permissions = $validated['permissions'];
        unset($validated['reader_ids'], $validated['lock_ids'], $validated['switch_ids'], $validated['permissions']);

        DB::transaction(function () use ($area, $validated, $readerIds, $lockIds, $switchIds, $permissions): void {
            $area->update($validated);

            $this->syncReaderAssignments($area->fresh(), $readerIds);
            $this->assignLocksToArea($area, $lockIds);
            $this->assignSwitchesToArea($area, $switchIds);
            $this->syncAreaPermissions($area, $permissions);
        });

        return redirect()->route('admin.access-areas.index')->with('status', 'Area updated successfully.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        Area::query()->whereKey($area->id)->delete();

        return redirect()->route('admin.access-areas.index')->with('status', 'Area deleted successfully.');
    }

    /**
     * @return array{name:string,identifier:string,metadata:array<string,mixed>,reader_ids:array<int,int>,lock_ids:array<int,int>,switch_ids:array<int,int>,permissions:array<int,array{individual_id:int,permission:string,metadata:array<string,mixed>}>}
     */
    private function validateAndNormalize(Request $request, ?Area $area = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('areas', 'identifier')->ignore($area?->id),
            ],
            'default_autolock_enabled' => ['required', 'boolean'],
            'default_autolock_duration' => ['required', 'integer', 'min:0'],
            'config_json' => ['nullable', 'string'],
            'metadata_json' => ['nullable', 'string'],
            'reader_ids' => ['array'],
            'reader_ids.*' => ['integer', Rule::exists('readers', 'id')],
            'lock_ids' => ['array'],
            'lock_ids.*' => ['integer', Rule::exists('locks', 'id')],
            'switch_ids' => ['array'],
            'switch_ids.*' => ['integer', Rule::exists('switches', 'id')],
            'permissions' => ['array'],
            'permissions.*.individual_id' => ['required', 'integer', Rule::exists('individuals', 'id')],
            'permissions.*.permission' => ['required', 'string', 'in:allow,deny'],
            'permissions.*.metadata_json' => ['nullable', 'string'],
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

        $readerIds = collect($validated['reader_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $lockIds = collect($validated['lock_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $switchIds = collect($validated['switch_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();

        $permissions = collect($validated['permissions'] ?? [])
            ->map(function (array $row, int $index): array {
                return [
                    'individual_id' => (int) $row['individual_id'],
                    'permission' => (string) $row['permission'],
                    'metadata' => $this->decodeJsonField($row['metadata_json'] ?? null, 'permissions.'.$index.'.metadata_json'),
                ];
            })
            ->values();

        $duplicateUserIds = $permissions
            ->pluck('individual_id')
            ->duplicates()
            ->unique()
            ->values();

        if ($duplicateUserIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Each access user can only appear once per area.',
            ]);
        }

        $config = $this->decodeJsonField($validated['config_json'] ?? null, 'config_json');
        $config = $this->ensureAreaAutolockDefaults($config);
        data_set($config, 'locking.autolock_enabled', (bool) $validated['default_autolock_enabled']);
        data_set($config, 'locking.autolock_duration', max(0, (int) $validated['default_autolock_duration']));

        return [
            'name' => $validated['name'],
            'identifier' => $identifier,
            'config' => $config,
            'metadata' => $this->decodeJsonField($validated['metadata_json'] ?? null, 'metadata_json'),
            'reader_ids' => $readerIds,
            'lock_ids' => $lockIds,
            'switch_ids' => $switchIds,
            'permissions' => $permissions->all(),
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

    /**
     * @param  array<int,int>  $readerIds
     */
    private function syncReaderAssignments(Area $area, array $readerIds): void
    {
        $unassignedAreaId = Area::ensureUnassignedArea()->id;

        Reader::query()
            ->where('area_id', $area->id)
            ->whereNotIn('id', $readerIds, 'and')
            ->update(['area_id' => $unassignedAreaId]);

        if ($readerIds !== []) {
            Reader::query()
                ->whereIn('id', $readerIds, 'and', false)
                ->update(['area_id' => $area->id]);
        }
    }

    /**
     * @param  array<int,int>  $lockIds
     */
    private function assignLocksToArea(Area $area, array $lockIds): void
    {
        if ($lockIds === []) {
            return;
        }

        Lock::query()
            ->whereIn('id', $lockIds, 'and', false)
            ->update(['area_id' => $area->id]);
    }

    /**
     * @param  array<int,int>  $switchIds
     */
    private function assignSwitchesToArea(Area $area, array $switchIds): void
    {
        if ($switchIds === []) {
            return;
        }

        PhysicalSwitch::query()
            ->whereIn('id', $switchIds, 'and', false)
            ->update(['area_id' => $area->id]);
    }

    /**
     * @param  array<int,array{individual_id:int,permission:string,metadata:array<string,mixed>}>  $permissions
     */
    private function syncAreaPermissions(Area $area, array $permissions): void
    {
        AreaPermission::query()->where('area_id', $area->id)->delete();

        foreach ($permissions as $permission) {
            AreaPermission::query()->create([
                'individual_id' => $permission['individual_id'],
                'area_id' => $area->id,
                'permission' => $permission['permission'],
                'metadata' => $permission['metadata'],
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function formData(?Area $area = null): array
    {
        $areaId = $area?->id;

        $readerIds = $areaId === null
            ? []
            : Reader::query()->where('area_id', $areaId)->pluck('id')->all();

        $lockIds = $areaId === null
            ? []
            : Lock::query()->where('area_id', $areaId)->pluck('id')->all();

        $permissionRows = $areaId === null
            ? []
            : AreaPermission::query()
                ->where('area_id', $areaId)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn (AreaPermission $permission): array => [
                    'individual_id' => $permission->individual_id,
                    'permission' => $permission->permission,
                    'metadata_json' => json_encode($permission->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ])
                ->values()
                ->all();

        $switchIds = $areaId === null
            ? []
            : PhysicalSwitch::query()->where('area_id', $areaId)->pluck('id')->all();

        return [
            'accessReaders' => Reader::query()
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'identifier', 'area_id']),
            'accessLocks' => Lock::query()
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'identifier', 'area_id']),
            'accessSwitches' => PhysicalSwitch::query()
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'identifier', 'area_id']),
            'accessUsers' => Individual::query()->orderBy('name', 'asc')->get(['id', 'name']),
            'areaReaderIds' => $readerIds,
            'areaLockIds' => $lockIds,
            'areaSwitchIds' => $switchIds,
            'areaPermissionRows' => $permissionRows,
            'defaultAutolockEnabled' => (bool) data_get($area?->config, 'locking.autolock_enabled', false),
            'defaultAutolockDuration' => max(0, (int) data_get($area?->config, 'locking.autolock_duration', 0)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function ensureAreaAutolockDefaults(array $config): array
    {
        if (! array_key_exists('locking', $config) || ! is_array($config['locking'])) {
            $config['locking'] = [];
        }

        if (! array_key_exists('autolock_enabled', $config['locking'])) {
            $config['locking']['autolock_enabled'] = false;
        }

        if (! array_key_exists('autolock_duration', $config['locking'])) {
            $config['locking']['autolock_duration'] = 0;
        }

        return $config;
    }

    private function resolveAreaPrimaryReader(Area $area): ?Reader
    {
        $readers = $area->relationLoaded('readers') ? $area->readers : $area->readers()->get();

        return $readers->sortBy('id')->first();
    }

    private function dispatchAreaLockCommand(
        Request $request,
        Area $area,
        int $targetPower,
        bool $allowAutoRelock,
        string $statusMessage,
    ): RedirectResponse {
        $reader = $this->resolveAreaPrimaryReader($area);

        if ($reader === null) {
            return redirect()->route('admin.access-areas.index')->with('status', 'Area has no assigned readers to control locks.');
        }

        ProcessReaderEvent::dispatch(
            null,
            $reader,
            $targetPower,
            $allowAutoRelock,
            'admin_area_control'
        );

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area->id,
            'access_lock_id' => $area->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'lock',
            'origin_id' => $area->primaryLock()?->id ?? $area->id,
            'origin_label' => $area->primaryLock()?->name ?? $area->name,
            'granted' => true,
            'status' => $targetPower === 1 ? 'admin_lock_requested' : 'admin_unlock_requested',
            'reason' => $targetPower === 1
                ? 'Area lock command requested from areas overview.'
                : 'Area unlock command requested from areas overview.',
            'metadata' => [
                'source' => 'admin',
                'event' => 'area_lock_toggle',
                'area_id' => $area->id,
                'area_identifier' => $area->identifier,
                'target_power' => $targetPower,
                'allow_auto_relock' => $allowAutoRelock,
            ],
            'ip_address' => $request->ip(),
        ]);

        PublishReaderState::dispatch($reader->fresh(), $targetPower);

        return redirect()->route('admin.access-areas.index')->with('status', $statusMessage);
    }
}
