<?php

namespace App\Services\AccessControl;

use App\Models\Hardware\Reader;
use App\Support\SignalValueMapper;
use Throwable;

class LockStatusResolver
{
    public function __construct(
        private readonly AccessBindingResolver $bindingResolver,
        private readonly OutputAdapterRegistry $outputAdapterRegistry,
    ) {}

    /**
     * @return array{state:string,label:string,badge:string,tag:?string,error:?string,adapter_type:?string,signal_reversed:bool}
     */
    public function resolve(Reader $reader): array
    {
        $binding = $this->bindingResolver->resolveLockPowerBinding($reader);

        if ($binding === null) {
            return [
                'state' => 'unknown',
                'label' => 'No lock binding configured',
                'badge' => 'secondary',
                'tag' => null,
                'error' => null,
                'adapter_type' => null,
                'signal_reversed' => false,
            ];
        }

        try {
            $adapter = $this->outputAdapterRegistry->resolve($binding->adapterType);
            $rawValue = $adapter->read($binding->channel, $binding->config);
            $locked = SignalValueMapper::toCanonicalBool($rawValue, $binding->signalReversed);

            if ($locked === true) {
                return [
                    'state' => 'locked',
                    'label' => 'Locked',
                    'badge' => 'danger',
                    'tag' => $binding->channel,
                    'error' => null,
                    'adapter_type' => $binding->adapterType,
                    'signal_reversed' => $binding->signalReversed,
                ];
            }

            if ($locked === false) {
                return [
                    'state' => 'unlocked',
                    'label' => 'Unlocked',
                    'badge' => 'success',
                    'tag' => $binding->channel,
                    'error' => null,
                    'adapter_type' => $binding->adapterType,
                    'signal_reversed' => $binding->signalReversed,
                ];
            }

            return [
                'state' => 'unknown',
                'label' => 'Unknown value: '.(is_scalar($rawValue) ? (string) $rawValue : gettype($rawValue)),
                'badge' => 'secondary',
                'tag' => $binding->channel,
                'error' => null,
                'adapter_type' => $binding->adapterType,
                'signal_reversed' => $binding->signalReversed,
            ];
        } catch (Throwable $e) {
            return [
                'state' => 'unknown',
                'label' => 'Unavailable',
                'badge' => 'secondary',
                'tag' => $binding->channel,
                'error' => $e->getMessage(),
                'adapter_type' => $binding->adapterType,
                'signal_reversed' => $binding->signalReversed,
            ];
        }
    }
}
