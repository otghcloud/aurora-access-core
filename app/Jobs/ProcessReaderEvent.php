<?php

namespace App\Jobs;

use App\Models\Access\Card;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;
use App\Services\AccessControl\AccessOutputOrchestrator;
use App\Services\AccessControl\AutolockSettingsResolver;
use App\Services\AccessControl\ExpectedLockStateStore;
use App\Services\AccessControlMqttPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessReaderEvent implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Give transient RTU/API failures time to recover instead of hard-failing immediately.
     */
    public int $tries = 6;

    /**
     * @var array<int,int>
     */
    public array $backoff = [5, 15, 30, 60, 120];

    public function __construct(
        public ?Card $accessCard,
        public Reader $accessReader,
        public ?int $targetValue = null,
        public bool $allowAutoRelock = true,
        public ?string $eventSource = null,
    ) {}

    /**
     * Create a new job instance.
     */
    public function handle(
        AccessOutputOrchestrator $outputOrchestrator,
        AutolockSettingsResolver $autolockSettingsResolver,
        AccessControlMqttPublisher $mqttPublisher,
        ExpectedLockStateStore $expectedLockStateStore,
    ): void {
        Log::info('process_reader_event.start', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'target_value' => $this->targetValue,
            'allow_auto_relock' => $this->allowAutoRelock,
            'queue_connection' => config('queue.default'),
        ]);

        $targetLocked = $this->targetValue === null ? null : ((int) $this->targetValue === 1);
        $result = $outputOrchestrator->setLockState($this->accessReader, $targetLocked);

        if ($result === null) {
            Log::warning('process_reader_event.missing_target_lock_binding', [
                'reader_id' => $this->accessReader->id,
                'reader_identifier' => $this->accessReader->identifier,
            ]);

            return;
        }

        $binding = $result['binding'];
        $bindingResults = is_array($result['bindings'] ?? null) ? $result['bindings'] : [];
        $currentLocked = $result['current_locked'];
        $newLocked = (bool) $result['new_locked'];
        $currentRaw = $result['current_raw'];
        $newWire = $result['new_wire'];
        $newValue = $newLocked ? 1 : 0;

        $targetChannels = collect($bindingResults)
            ->map(fn (array $row): ?string => (string) ($row['binding']->channel ?? ''))
            ->filter(fn (?string $channel): bool => $channel !== null && $channel !== '')
            ->values()
            ->all();

        $bindingCount = count($bindingResults) > 0 ? count($bindingResults) : 1;

        Log::info('process_reader_event.lock_tag_updated', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'binding_count' => $bindingCount,
            'target_channels' => $targetChannels,
            'adapter_type' => $binding->adapterType,
            'target_channel' => $binding->channel,
            'signal_reversed' => $binding->signalReversed,
            'previous_canonical_locked' => $currentLocked,
            'new_canonical_locked' => $newLocked,
            'previous_raw_value' => is_scalar($currentRaw) ? (string) $currentRaw : null,
            'new_wire_value' => is_scalar($newWire) ? (string) $newWire : null,
        ]);

        $persistedReader = $this->accessReader->fresh() ?? $this->accessReader;
        $expectedLockStateStore->storeExpectedLockPower($persistedReader, $newValue, $this->determineEventSource());

        $mqttPublisher->publishReaderState($this->accessReader, $newValue);

        $area = $this->accessReader->area;
        $lock = $area?->primaryLock();

        Event::create([
            'access_card_id' => $this->accessCard?->id,
            'access_area_id' => $area?->id,
            'access_lock_id' => $lock?->id,
            'user_id' => $this->accessCard?->user_id,
            'card_number' => $this->accessCard?->card_number,
            'origin_type' => 'lock',
            'origin_id' => $lock?->id ?? $this->accessReader->id,
            'origin_label' => $lock?->name ?? $this->accessReader->name,
            'granted' => true,
            'status' => $newValue === 0 ? 'lock_unlocked' : 'lock_locked',
            'reason' => $newValue === 0 ? 'Lock power set to unlock.' : 'Lock power set to lock.',
            'metadata' => [
                'source' => $this->determineEventSource(),
                'event' => 'lock_power_updated',
                'adapter_type' => $binding->adapterType,
                'action_key' => $binding->actionKey,
                'binding_count' => $bindingCount,
                'target_channels' => $targetChannels,
                'target_channel' => $binding->channel,
                'signal_reversed' => $binding->signalReversed,
                'previous_canonical_locked' => $currentLocked,
                'new_canonical_locked' => $newLocked,
                'previous_raw_value' => is_scalar($currentRaw) ? (string) $currentRaw : null,
                'new_wire_value' => is_scalar($newWire) ? (string) $newWire : null,
                'target_value' => $this->targetValue,
                'allow_auto_relock' => $this->allowAutoRelock,
            ],
            'ip_address' => null,
        ]);

        $autolockSettings = $autolockSettingsResolver->resolveForReader($this->accessReader);
        $autolockEnabled = (bool) ($autolockSettings['enabled'] ?? false);
        $autolockDuration = max(0, (int) ($autolockSettings['duration'] ?? 0));

        // Unlock action should auto-relock when enabled.
        if ($this->allowAutoRelock && $autolockEnabled && ! $newLocked) {
            Log::info('process_reader_event.auto_relock_prepare', [
                'reader_id' => $this->accessReader->id,
                'reader_identifier' => $this->accessReader->identifier,
                'delay_seconds' => $autolockDuration,
                'autolock_source' => $autolockSettings['source'] ?? 'unknown',
                'queue_connection' => config('queue.default'),
            ]);

            self::dispatch($this->accessCard, $this->accessReader, 1, false, 'auto_relock')
                ->delay(now()->addSeconds($autolockDuration));

            Log::info('process_reader_event.auto_relock_scheduled', [
                'reader_id' => $this->accessReader->id,
                'reader_identifier' => $this->accessReader->identifier,
                'delay_seconds' => $autolockDuration,
            ]);

            return;
        }

        Log::info('process_reader_event.auto_relock_skipped', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'allow_auto_relock' => $this->allowAutoRelock,
            'autolock_enabled' => $autolockEnabled,
            'autolock_source' => $autolockSettings['source'] ?? 'unknown',
            'new_value' => $newValue,
        ]);

    }

    public function failed(Throwable $exception): void
    {
        Log::error('process_reader_event.failed', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'target_value' => $this->targetValue,
            'allow_auto_relock' => $this->allowAutoRelock,
            'queue_connection' => config('queue.default'),
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        Event::create([
            'access_card_id' => $this->accessCard?->id,
            'access_area_id' => $this->accessReader->area_id,
            'access_lock_id' => $this->accessReader->area?->primaryLock()?->id,
            'user_id' => $this->accessCard?->user_id,
            'card_number' => $this->accessCard?->card_number,
            'origin_type' => 'lock',
            'origin_id' => $this->accessReader->area?->primaryLock()?->id ?? $this->accessReader->id,
            'origin_label' => $this->accessReader->area?->primaryLock()?->name ?? $this->accessReader->name,
            'granted' => false,
            'status' => 'lock_command_failed',
            'reason' => $exception->getMessage(),
            'metadata' => [
                'source' => $this->determineEventSource(),
                'event' => 'lock_power_update_failed',
                'target_value' => $this->targetValue,
                'allow_auto_relock' => $this->allowAutoRelock,
            ],
            'ip_address' => null,
        ]);
    }

    private function determineEventSource(): string
    {
        if (is_string($this->eventSource) && trim($this->eventSource) !== '') {
            return trim($this->eventSource);
        }

        if ($this->accessCard !== null) {
            return 'access_validation';
        }

        if ($this->targetValue === 0) {
            return 'mqtt_push_request';
        }

        return 'system';
    }
}
