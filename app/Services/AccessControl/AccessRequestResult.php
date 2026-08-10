<?php

namespace App\Services\AccessControl;

use App\Enums\AccessControl\AccessEventStatus;
use App\Models\Access\Card;
use App\Models\Access\Event;
use App\Models\Hardware\Reader;

class AccessRequestResult
{
    public function __construct(
        public readonly AccessEventStatus $status,
        public readonly ?string $reason,
        public readonly ?Card $accessCard,
        public readonly ?Reader $accessReader,
        public readonly Event $event,
    ) {}

    public function isGranted(): bool
    {
        return $this->status === AccessEventStatus::SUCCESS;
    }
}
