<?php

namespace App\Console\Commands;

use App\Services\AccessControlHealthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:health-access-control {--queue= : Override queue name for depth check} {--reader= : Reader identifier to use for MQTT state probe} {--json : Output machine-readable JSON}')]
#[Description('Run health checks for access-control queue, Redis, supervisor processes, and MQTT state publishing')]
class AccessControlHealthCheck extends Command
{
    public function handle(): int
    {
        $jsonOutput = (bool) $this->option('json');
        $payload = app(AccessControlHealthService::class)->generate(
            is_string($this->option('queue')) ? $this->option('queue') : null,
            is_string($this->option('reader')) ? $this->option('reader') : null,
        );

        if (! $jsonOutput) {
            $this->line('Access Control Health Check');
            $this->line('Generated at: '.now()->toDateTimeString());
            $this->newLine();
        }

        if ($jsonOutput) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $payload['critical_failures'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $tableRows = array_map(
            fn (array $check): array => [$check['name'], $check['status'], $check['details']],
            $payload['checks']
        );

        $this->table(['Check', 'Status', 'Details'], $tableRows);

        if ($payload['critical_failures'] > 0) {
            $this->error(sprintf('Health check failed: %d critical check(s) failed.', $payload['critical_failures']));

            return self::FAILURE;
        }

        $this->info('Health check passed with no critical failures.');

        return self::SUCCESS;
    }
}
