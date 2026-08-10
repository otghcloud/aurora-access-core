<?php

namespace App\Console\Commands;

use App\Services\AccessControl\AccessControlSettingsRepository;
use App\Services\Supervisor\AccessControlSupervisorConfigManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:rebuild-access-control-supervisor {--path= : Optional output file path} {--dry-run : Print generated config to stdout without writing} {--apply-supervisorctl : Run supervisorctl reread/update after rebuild} {--skip-supervisorctl : Skip supervisorctl commands after rebuild} {--strict-supervisorctl : Fail command when supervisorctl commands fail}')]
#[Description('Rebuild deploy/supervisor/access-control.conf from current readers/sources state')]
class RebuildAccessControlSupervisorConfig extends Command
{
    public function handle(AccessControlSupervisorConfigManager $manager): int
    {
        $path = $this->option('path');

        if (is_string($path) && trim($path) === '') {
            $path = null;
        }

        if ((bool) $this->option('dry-run')) {
            $this->line($manager->render());

            return self::SUCCESS;
        }

        $outputPath = $manager->rebuild(is_string($path) ? trim($path) : null);
        $this->info('Supervisor config rebuilt: '.$outputPath);

        $shouldApply = $this->resolveShouldApplySupervisorctl();
        if (! $shouldApply) {
            return self::SUCCESS;
        }

        $result = $manager->applySupervisorChanges();

        foreach ($result['steps'] as $step) {
            if ($step['ok']) {
                $this->info('Supervisor step OK: '.$step['command']);

                continue;
            }

            $this->warn('Supervisor step failed: '.$step['command']);
            if ($step['output'] !== '') {
                $this->line($step['output']);
            }
        }

        if (! $result['ok'] && (bool) $this->option('strict-supervisorctl')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveShouldApplySupervisorctl(): bool
    {
        if ((bool) $this->option('skip-supervisorctl')) {
            return false;
        }

        if ((bool) $this->option('apply-supervisorctl')) {
            return true;
        }

        $settings = app(AccessControlSettingsRepository::class);

        return (bool) $settings->get('supervisor.apply_after_rebuild', true);
    }
}
