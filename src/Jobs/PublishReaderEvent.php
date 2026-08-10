<?php

namespace OTGH\AccessControl\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Services\AccessControlMqttPublisher;
use Throwable;

class PublishReaderEvent implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int,int>
     */
    public array $backoff = [5, 15];

    /**
     * @param  array<string,mixed>  $extra
     */
    public function __construct(
        public Reader $accessReader,
        public string $eventType,
        public array $extra = [],
    ) {}

    public function handle(): void
    {
        $this->accessReader->refresh();

        app(AccessControlMqttPublisher::class)->publishTransientEvent(
            $this->accessReader,
            array_merge(['type' => $this->eventType], $this->extra),
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('publish_reader_event.failed', [
            'reader_id' => $this->accessReader->id,
            'reader_identifier' => $this->accessReader->identifier,
            'event_type' => $this->eventType,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
