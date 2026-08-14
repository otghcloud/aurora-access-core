<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use Illuminate\Support\Facades\Schema;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;

class LockStateStore
{
    /**
     * @return array{state:string,confidence:string,updated_at:?string,source:?string}
     */
    public function forLock(Lock $lock): array
    {
        $state = data_get($lock->metadata, 'lock_state');
        $power = data_get($state, 'expected_lock_power');

        return [
            'state' => $power === 1 ? 'locked' : ($power === 0 ? 'unlocked' : 'unknown'),
            'confidence' => $power === null ? 'low' : 'medium',
            'updated_at' => data_get($state, 'updated_at'),
            'source' => data_get($state, 'source'),
        ];
    }

    public function storeForReader(Reader $reader, int $lockPower, string $source = 'system'): void
    {
        $lockIds = [];

        if (Schema::hasTable('reader_lock_bindings')) {
            $lockIds = ReaderLockBinding::query()
                ->where('reader_id', $reader->id)
                ->where('enabled', true)
                ->pluck('lock_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        if ($lockIds === []) {
            $primaryLock = $reader->area?->primaryLock();
            $lockIds = $primaryLock === null ? [] : [$primaryLock->id];
        }

        if ($lockIds === []) {
            return;
        }

        Lock::query()
            ->whereIn('id', $lockIds)
            ->get()
            ->each(function (Lock $lock) use ($lockPower, $source): void {
                $metadata = is_array($lock->metadata) ? $lock->metadata : [];
                $metadata['lock_state'] = array_replace(
                    is_array($metadata['lock_state'] ?? null) ? $metadata['lock_state'] : [],
                    [
                        'expected_lock_power' => $lockPower === 0 ? 0 : 1,
                        'updated_at' => now()->toIso8601String(),
                        'source' => trim($source) !== '' ? trim($source) : 'system',
                    ],
                );
                $lock->update(['metadata' => $metadata]);
            });
    }
}
