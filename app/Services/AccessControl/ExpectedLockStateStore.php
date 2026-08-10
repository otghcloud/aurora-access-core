<?php

namespace App\Services\AccessControl;

use App\Models\Hardware\Reader;

class ExpectedLockStateStore
{
    public function expectedLockPower(Reader $reader): ?int
    {
        $value = data_get($reader->metadata, 'lock_state.expected_lock_power');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return in_array($normalized, [0, 1], true) ? $normalized : null;
    }

    public function storeExpectedLockPower(Reader $reader, int $lockPower, string $source = 'system'): void
    {
        $metadata = is_array($reader->metadata) ? $reader->metadata : [];
        $metadata['lock_state'] = array_replace(
            is_array($metadata['lock_state'] ?? null) ? $metadata['lock_state'] : [],
            [
                'expected_lock_power' => $lockPower === 0 ? 0 : 1,
                'updated_at' => now()->toIso8601String(),
                'source' => trim($source) !== '' ? trim($source) : 'system',
            ],
        );

        $reader->metadata = $metadata;
        $reader->save();
    }
}
