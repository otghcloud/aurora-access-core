<?php

namespace OTGH\AccessControl\Core\Services\AccessControl;

use RuntimeException;

class OutputAdapterRegistry
{
    /**
     * @var array<string,OutputAdapterInterface>
     */
    private array $adapters = [];

    public function __construct() {}

    public function register(OutputAdapterInterface $adapter): void
    {
        $key = strtolower(trim($adapter->type()));

        if ($key === '') {
            throw new RuntimeException('Output adapter type cannot be empty.');
        }

        $this->adapters[$key] = $adapter;
    }

    public function resolve(string $adapterType): OutputAdapterInterface
    {
        $key = strtolower(trim($adapterType));

        if (! isset($this->adapters[$key])) {
            throw new RuntimeException('Unsupported output adapter type ['.$adapterType.'].');
        }

        return $this->adapters[$key];
    }
}
