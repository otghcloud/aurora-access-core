<?php

namespace OTGH\AccessControl\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use Throwable;

class PublishLocksForReader implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var array<int,int> */
    public array $backoff = [2, 5, 15];

    public function __construct(public Reader $reader) {}

    public function handle(AccessControlMqttPublisher $mqttPublisher): void
    {
        $mqttPublisher->publishLocksForReader($this->reader->fresh() ?? $this->reader);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('publish_locks_for_reader.failed', [
            'reader_id' => $this->reader->id,
            'reader_identifier' => $this->reader->identifier,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
