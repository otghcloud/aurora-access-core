<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use OTGH\AccessControl\Core\Models\Access\Area;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;

class AutolockSettingsResolver
{
    /**
     * @return array{enabled:bool,duration:int,source:string,area:Area|null,lock:Lock|null}
     */
    public function resolveForReader(Reader $reader): array
    {
        $area = $reader->relationLoaded('area')
            ? $reader->area
            : $reader->area()->with('locks')->first();
        $lock = $area?->locks?->firstWhere('is_primary', true) ?? $area?->locks?->sortBy('id')->first();

        return $this->resolveForAreaAndLock($area, $lock);
    }

    /**
     * @return array{enabled:bool,duration:int,source:string,area:Area|null,lock:Lock|null}
     */
    public function resolveForAreaAndLock(?Area $area, ?Lock $lock): array
    {
        $areaEnabled = $area?->autolockEnabled() ?? false;
        $areaDuration = $area?->autolockDuration() ?? 0;

        $overrideEnabled = $lock?->autolockOverrideEnabled();
        $overrideDuration = $lock?->autolockOverrideDuration();

        $enabled = $overrideEnabled ?? $areaEnabled;
        $duration = $overrideDuration ?? $areaDuration;

        return [
            'enabled' => (bool) $enabled,
            'duration' => max(0, (int) $duration),
            'source' => ($overrideEnabled !== null || $overrideDuration !== null) ? 'lock_override' : 'area_default',
            'area' => $area,
            'lock' => $lock,
        ];
    }
}
