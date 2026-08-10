<?php

namespace OTGH\AccessControl\Core\Observers;

use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessControlSettingsRepository;
use OTGH\AccessControl\Core\Services\Supervisor\AccessControlSupervisorConfigManager;
use Throwable;

class AccessReaderObserver
{
    public function saved(Reader $reader): void
    {
        if (! $reader->wasRecentlyCreated && ! $reader->wasChanged(['identifier', 'config'])) {
            return;
        }

        $this->rebuildSupervisorConfig();
    }

    public function deleted(Reader $reader): void
    {
        $this->rebuildSupervisorConfig();
    }

    public function restored(Reader $reader): void
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
                    Log::warning('access_control.supervisor.apply_failed_after_reader_change', [
                        'steps' => $result['steps'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('access_control.supervisor.rebuild_failed_after_reader_change', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
