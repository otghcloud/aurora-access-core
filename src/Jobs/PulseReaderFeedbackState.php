<?php

namespace OTGH\AccessControl\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControl\AccessBindingResolver;
use OTGH\AccessControl\Core\Services\AccessControl\AccessOutputOrchestrator;
use Throwable;

class PulseReaderFeedbackState implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 6;

    /**
     * @var array<int,int>
     */
    public array $backoff = [5, 15, 30, 60, 120];

    public function __construct(
        public Reader $accessReader,
        public int $targetValue = 1,
        public ?string $eventSource = null,
    ) {}

    public function handle(AccessOutputOrchestrator $outputOrchestrator): void
    {
        $targetActive = $this->targetValue === 1;
        $result = $outputOrchestrator->setReaderFeedbackState($this->accessReader, $targetActive);

        if ($result === null) {
            Log::info('pulse_reader_feedback_state.skipped', [
                'reader_id' => $this->accessReader->id,
                'reader_identifier' => $this->accessReader->identifier,
                'target_value' => $this->targetValue,
                'reason' => 'missing_feedback_state_binding',
            ]);

            return;
        }

        $feedbackDuration = max(0, (int) data_get($this->accessReader->config, 'general.feedback_state_duration', 5));

        $binding = $result['binding'];
        $newWire = $result['new_wire'];

        Log::info('pulse_reader_feedback_state.updated', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'adapter_type' => $binding->adapterType,
            'action_key' => $binding->actionKey,
            'feedback_channel' => $binding->channel,
            'signal_reversed' => $binding->signalReversed,
            'target_value' => $this->targetValue,
            'target_active' => $targetActive,
            'new_wire_value' => is_scalar($newWire) ? (string) $newWire : null,
            'duration_seconds' => $feedbackDuration,
        ]);

        if ($this->targetValue !== 1) {
            return;
        }

        self::dispatch($this->accessReader, 0, 'feedback_reset')
            ->delay(now()->addSeconds($feedbackDuration));

        Log::info('pulse_reader_feedback_state.reset_scheduled', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'feedback_channel' => $binding->channel,
            'delay_seconds' => $feedbackDuration,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $feedbackBinding = app(AccessBindingResolver::class)->resolveReaderFeedbackBinding($this->accessReader);

        Log::error('pulse_reader_feedback_state.failed', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'target_value' => $this->targetValue,
            'adapter_type' => $feedbackBinding?->adapterType,
            'feedback_channel' => $feedbackBinding?->channel,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        Event::create([
            'access_card_id' => null,
            'access_area_id' => $this->accessReader->area_id,
            'access_lock_id' => $this->accessReader->area?->primaryLock()?->id,
            'user_id' => null,
            'card_number' => null,
            'origin_type' => 'reader',
            'origin_id' => $this->accessReader->id,
            'origin_label' => $this->accessReader->name,
            'granted' => false,
            'status' => 'reader_feedback_failed',
            'reason' => $exception->getMessage(),
            'metadata' => [
                'source' => $this->determineEventSource(),
                'event' => 'feedback_state_update_failed',
                'target_value' => $this->targetValue,
                'adapter_type' => $feedbackBinding?->adapterType,
                'action_key' => $feedbackBinding?->actionKey,
                'feedback_channel' => $feedbackBinding?->channel,
                'signal_reversed' => $feedbackBinding?->signalReversed,
            ],
            'ip_address' => null,
        ]);
    }

    private function determineEventSource(): string
    {
        if (is_string($this->eventSource) && trim($this->eventSource) !== '') {
            return trim($this->eventSource);
        }

        return 'access_validation';
    }
}
