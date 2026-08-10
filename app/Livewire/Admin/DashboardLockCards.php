<?php

namespace App\Livewire\Admin;

use App\Jobs\ProcessReaderEvent;
use App\Jobs\PublishReaderState;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\AutolockSettingsResolver;
use App\Services\AccessControl\LockStatusResolver;
use Illuminate\Support\Collection;
use Livewire\Component;

class DashboardLockCards extends Component
{
    public ?string $statusMessage = null;

    public function toggleLock(int $readerId): void
    {
        $accessReader = Reader::findOrFail($readerId);
        $status = $this->resolveSingleLockStatus($accessReader);

        if (($status['state'] ?? 'unknown') === 'unknown') {
            $this->statusMessage = 'Unable to toggle lock: current lock state is unavailable.';

            return;
        }

        $isCurrentlyLocked = ($status['state'] ?? null) === 'locked';
        $targetValue = $isCurrentlyLocked ? 0 : 1;
        $allowAutoRelock = $targetValue === 0;
        $area = $accessReader->area;
        $primaryLock = $area?->primaryLock();

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $area?->id,
            'access_lock_id' => $primaryLock?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'lock',
            'origin_id' => $primaryLock?->id ?? $accessReader->id,
            'origin_label' => $primaryLock?->name ?? $accessReader->name,
            'granted' => true,
            'status' => $targetValue === 1 ? 'admin_lock_requested' : 'admin_unlock_requested',
            'reason' => $targetValue === 1
                ? 'Lock requested via dashboard lock card.'
                : 'Unlock requested via dashboard lock card.',
            'metadata' => [
                'source' => 'admin',
                'event' => $targetValue === 1 ? 'lock_requested' : 'unlock_requested',
                'allow_auto_relock' => $allowAutoRelock,
            ],
            'ip_address' => request()->ip(),
        ]);

        ProcessReaderEvent::dispatch(null, $accessReader, $targetValue, $allowAutoRelock, 'admin');

        $this->statusMessage = $targetValue === 1 ? 'Lock command queued.' : 'Unlock command queued.';
    }

    public function toggleAutolock(int $readerId): void
    {
        $accessReader = Reader::findOrFail($readerId);
        $area = $accessReader->area;

        if (! $area) {
            $this->statusMessage = 'Reader must be assigned to an area before toggling auto-lock defaults.';

            return;
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
                ? 'Auto-lock enabled via dashboard lock card.'
                : 'Auto-lock disabled via dashboard lock card.',
            'metadata' => [
                'source' => 'admin',
                'event' => 'autolock_updated',
                'autolock_enabled' => $updated,
                'autolock_duration' => $duration,
                'autolock_scope' => 'area_default',
                'area_id' => $area->id,
            ],
            'ip_address' => request()->ip(),
        ]);

        Reader::query()
            ->where('area_id', $area->id)
            ->get()
            ->each(fn (Reader $reader) => PublishReaderState::dispatch($reader->fresh()));

        $this->statusMessage = $updated ? 'Auto-lock enabled.' : 'Auto-lock disabled.';
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function lockCards(): array
    {
        $readers = Reader::query()->with('area.locks')->latest('id')->get();
        $statuses = $this->resolveLockStatuses($readers);
        $autolockResolver = app(AutolockSettingsResolver::class);

        return $readers->map(function (Reader $accessReader) use ($statuses, $autolockResolver): array {
            $config = is_array($accessReader->config) ? $accessReader->config : [];
            $metadata = is_array($accessReader->metadata) ? $accessReader->metadata : [];
            $autolock = $autolockResolver->resolveForReader($accessReader);
            $expectedLockPower = $this->normalizeExpectedLockPower(data_get($metadata, 'lock_state.expected_lock_power'));
            $currentState = (string) ($statuses[$accessReader->id]['state'] ?? 'unknown');
            $expectedState = match ($expectedLockPower) {
                1 => 'locked',
                0 => 'unlocked',
                default => null,
            };
            $stateMatchesExpected = $expectedState !== null
                && in_array($currentState, ['locked', 'unlocked'], true)
                ? $currentState === $expectedState
                : null;

            return [
                'reader' => $accessReader,
                'primary_lock' => $accessReader->area?->locks?->firstWhere('is_primary', true),
                'status' => $statuses[$accessReader->id] ?? ['state' => 'unknown', 'label' => 'Unknown', 'badge' => 'secondary', 'error' => null],
                'autolock_enabled' => (bool) ($autolock['enabled'] ?? false),
                'autolock_duration' => max(0, (int) ($autolock['duration'] ?? 0)),
                'autolock_source' => (string) ($autolock['source'] ?? 'area_default'),
                'expected_lock_power' => $expectedLockPower,
                'expected_lock_label' => match ($expectedLockPower) {
                    1 => 'Locked',
                    0 => 'Unlocked',
                    default => 'Unknown',
                },
                'expected_lock_updated_at' => $this->normalizeTimestamp(data_get($metadata, 'lock_state.updated_at')),
                'expected_lock_source' => $this->nullableString(data_get($metadata, 'lock_state.source')),
                'state_matches_expected' => $stateMatchesExpected,
            ];
        })->all();
    }

    private function normalizeExpectedLockPower(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return in_array($normalized, [0, 1], true) ? $normalized : null;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  Collection<int, Reader>  $readers
     * @return array<int, array<string,mixed>>
     */
    private function resolveLockStatuses(Collection $readers): array
    {
        $statuses = [];

        $resolver = app(LockStatusResolver::class);

        foreach ($readers as $reader) {
            $statuses[$reader->id] = $resolver->resolve($reader);
        }

        return $statuses;
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveSingleLockStatus(Reader $reader): array
    {
        return app(LockStatusResolver::class)->resolve($reader);
    }

    public function render()
    {
        return view('livewire.admin.dashboard-lock-cards', [
            'lockCards' => $this->lockCards(),
        ]);
    }
}
