<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use OTGH\AccessControl\Core\Enums\AccessControl\AccessEventStatus;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Access\Event;
use OTGH\AccessControl\Core\Models\Hardware\Reader;

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
