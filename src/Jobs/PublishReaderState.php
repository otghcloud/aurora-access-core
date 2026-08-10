<?php

namespace OTGH\AccessControl\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use Throwable;

class PublishReaderState implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var array<int,int> */
    public array $backoff = [2, 5, 15];

    public function __construct(
        public Reader $accessReader,
        public ?int $knownLockPower = null,
    ) {}

    public function handle(): void
    {
        app(AccessControlMqttPublisher::class)->publishReaderState($this->accessReader->fresh(), $this->knownLockPower);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('publish_reader_state.failed', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'known_lock_power' => $this->knownLockPower,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
