<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

interface OutputAdapterInterface
{
    public function type(): string;

    /**
     * @param  array<string,mixed>  $bindingConfig
     */
    public function read(string $channel, array $bindingConfig = []): mixed;

    /**
     * @param  array<string,mixed>  $bindingConfig
     */
    public function write(string $channel, mixed $value, array $bindingConfig = []): void;
}
