<?php

namespace OTGH\AccessControl\Core\Observers;

use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;
use OTGH\AccessControl\Core\Services\Supervisor\AccessControlSupervisorConfigManager;
use Throwable;

class AccessSourceObserver
{
    public function saved(Source $source): void
    {
        if (! $source->wasRecentlyCreated && ! $source->wasChanged(['type', 'enabled'])) {
            return;
        }

        $this->rebuildSupervisorConfig();
    }

    public function deleted(Source $source): void
    {
        $this->rebuildSupervisorConfig();
    }

    public function restored(Source $source): void
    {
        $this->rebuildSupervisorConfig();
    }

    private function rebuildSupervisorConfig(): void
    {
        $settings = app(AccessControlSettingsRepository::class);

        if (! (bool) $settings->get('supervisor.auto_rebuild', true)) {
            return;
        }

        try {
            $manager = app(AccessControlSupervisorConfigManager::class);
            $manager->rebuild();

            if ((bool) $settings->get('supervisor.auto_apply', false)) {
                $result = $manager->applySupervisorChanges();
                if (! $result['ok']) {
                    Log::warning('access_control.supervisor.apply_failed_after_source_change', [
                        'steps' => $result['steps'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('access_control.supervisor.rebuild_failed_after_source_change', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
