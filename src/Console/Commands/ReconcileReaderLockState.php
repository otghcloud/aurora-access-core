<?php

namespace OTGH\AccessControl\Core\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessOutputOrchestrator;
use OTGH\AccessControl\Core\Services\AccessControl\ExpectedLockStateStore;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use Throwable;

#[Signature('app:reconcile-reader-lock-state {--reader= : Limit reconciliation to a single reader identifier} {--dry-run : Report drift without correcting it}')]
#[Description('Persist observed lock state and reconcile hardware lock outputs to the last expected state')]
class ReconcileReaderLockState extends Command
{
    public function handle(
        AccessOutputOrchestrator $outputOrchestrator,
        ExpectedLockStateStore $expectedLockStateStore,
        AccessControlMqttPublisher $mqttPublisher,
    ): int {
        $readerIdentifier = $this->option('reader');
        $dryRun = (bool) $this->option('dry-run');

        $readers = Reader::query()
            ->when(is_string($readerIdentifier) && trim($readerIdentifier) !== '', function ($query) use ($readerIdentifier) {
                $query->where('identifier', trim($readerIdentifier));
            })
            ->orderBy('name')
            ->get();

        if ($readers->isEmpty()) {
            $this->warn('No readers available for lock-state reconciliation.');

            return self::SUCCESS;
        }

        $seeded = 0;
        $reconciled = 0;
        $driftDetected = 0;
        $failures = 0;

        foreach ($readers as $reader) {
            try {
                $freshReader = $reader->fresh();

                if (! $freshReader instanceof Reader) {
                    throw new \RuntimeException('Unable to reload reader from the database.');
                }

                $expected = $expectedLockStateStore->expectedLockPower($freshReader);
                $actualLocked = $outputOrchestrator->readLockState($freshReader);
                $actual = $actualLocked === null ? null : ($actualLocked ? 1 : 0);

                if ($expected === null) {
                    if ($actual === null) {
                        $this->line(sprintf('[SKIP] %s (%s) expected=unset actual=unknown', $freshReader->name, $freshReader->identifier));

                        continue;
                    }

                    if (! $dryRun) {
                        $expectedLockStateStore->storeExpectedLockPower($freshReader, $actual, 'observed_lock_state');
                    }

                    $seeded++;
                    $this->line(sprintf(
                        '[%s] %s (%s) seeded expected=%d from live lock state',
                        $dryRun ? 'DRY-RUN' : 'SEEDED',
                        $freshReader->name,
                        $freshReader->identifier,
                        $actual,
                    ));

                    continue;
                }

                if ($actual === null) {
                    $this->warn(sprintf('[UNAVAILABLE] %s (%s) expected=%d actual=unknown', $freshReader->name, $freshReader->identifier, $expected));

                    continue;
                }

                if ($actual === $expected) {
                    $this->line(sprintf('[IN SYNC] %s (%s) lock_power=%d', $freshReader->name, $freshReader->identifier, $actual));

                    continue;
                }

                $driftDetected++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRIFT] %s (%s) expected=%d actual=%d action=would_reconcile',
                        $freshReader->name,
                        $freshReader->identifier,
                        $expected,
                        $actual,
                    ));

                    continue;
                }

                $result = $outputOrchestrator->setLockState($freshReader, $expected === 1);

                if ($result === null) {
                    throw new \RuntimeException('Reader has no lock power binding configured.');
                }

                $expectedLockStateStore->storeExpectedLockPower($freshReader, $expected, 'lock_state_reconciled');
                $mqttPublisher->publishLocksForReader($freshReader->fresh() ?? $freshReader);

                Event::create([
                    'access_card_id' => null,
                    'access_area_id' => $freshReader->area_id,
                    'access_lock_id' => $freshReader->area?->primaryLock()?->id,
                    'user_id' => null,
                    'card_number' => null,
                    'origin_type' => 'lock',
                    'origin_id' => $freshReader->area?->primaryLock()?->id ?? $freshReader->id,
                    'origin_label' => $freshReader->area?->primaryLock()?->name ?? $freshReader->name,
                    'granted' => true,
                    'status' => 'lock_state_reconciled',
                    'reason' => $expected === 1
                        ? 'Lock state restored to locked after drift detected.'
                        : 'Lock state restored to unlocked after drift detected.',
                    'metadata' => [
                        'source' => 'lock_state_reconciler',
                        'event' => 'lock_state_reconciled',
                        'expected_lock_power' => $expected,
                        'actual_lock_power' => $actual,
                    ],
                    'ip_address' => null,
                ]);

                $reconciled++;

                Log::info('lock_state.reconciled', [
                    'reader_id' => $freshReader->id,
                    'reader_identifier' => $freshReader->identifier,
                    'expected_lock_power' => $expected,
                    'actual_lock_power' => $actual,
                ]);

                $this->line(sprintf(
                    '[RECONCILED] %s (%s) expected=%d actual=%d',
                    $freshReader->name,
                    $freshReader->identifier,
                    $expected,
                    $actual,
                ));
            } catch (Throwable $e) {
                $failures++;

                Log::warning('lock_state.reconcile_failed', [
                    'reader_id' => $reader->id,
                    'reader_identifier' => $reader->identifier,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                $this->warn(sprintf('Failed to reconcile %s (%s): %s', $reader->name, $reader->identifier, $e->getMessage()));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Lock-state reconciliation complete. seeded=%d reconciled=%d drift_detected=%d failures=%d dry_run=%s',
            $seeded,
            $reconciled,
            $driftDetected,
            $failures,
            $dryRun ? 'yes' : 'no',
        ));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
