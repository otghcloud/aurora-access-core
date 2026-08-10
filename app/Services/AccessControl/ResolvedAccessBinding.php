<?php

namespace App\Services\AccessControl;

class ResolvedAccessBinding
{
    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $metadata
     */
    public function __construct(
        public readonly string $adapterType,
        public readonly string $actionKey,
        public readonly string $channel,
        public readonly bool $signalReversed,
        public readonly array $config = [],
        public readonly array $metadata = [],
    ) {}
}
