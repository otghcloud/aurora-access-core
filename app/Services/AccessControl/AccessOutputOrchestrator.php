<?php

namespace App\Services\AccessControl;

use App\Models\Hardware\Reader;
use App\Support\SignalValueMapper;

class AccessOutputOrchestrator
{
    public function __construct(
        private readonly AccessBindingResolver $bindingResolver,
        private readonly OutputAdapterRegistry $outputAdapterRegistry,
    ) {}

    /**
     * @return array{binding:ResolvedAccessBinding,current_locked:?bool,new_locked:bool,current_raw:mixed,new_wire:mixed,bindings:array<int,array{binding:ResolvedAccessBinding,current_locked:?bool,current_raw:mixed,new_wire:mixed}>}|null
     */
    public function setLockState(Reader $reader, ?bool $targetLocked = null): ?array
    {
        $bindings = $this->bindingResolver->resolveLockPowerBindings($reader);

        if ($bindings === []) {
            return null;
        }

        $newLocked = $targetLocked;

        $bindingResults = [];

        foreach ($bindings as $binding) {
            $adapter = $this->outputAdapterRegistry->resolve($binding->adapterType);
            $currentRaw = $adapter->read($binding->channel, $binding->config);
            $currentLocked = SignalValueMapper::toCanonicalBool($currentRaw, $binding->signalReversed);

            if ($newLocked === null && $currentLocked !== null) {
                $newLocked = ! $currentLocked;
            }

            $bindingResults[] = [
                'binding' => $binding,
                'current_locked' => $currentLocked,
                'current_raw' => $currentRaw,
                'new_wire' => null,
            ];
        }

        if ($newLocked === null) {
            $newLocked = true;
        }

        foreach ($bindingResults as &$bindingResult) {
            $binding = $bindingResult['binding'];
            $adapter = $this->outputAdapterRegistry->resolve($binding->adapterType);
            $wireOnValue = data_get($binding->config, 'signal.wire_on_value', data_get($binding->config, 'wire_on_value', 1));
            $wireOffValue = data_get($binding->config, 'signal.wire_off_value', data_get($binding->config, 'wire_off_value', 0));
            $newWireValue = SignalValueMapper::fromCanonicalBool(
                $newLocked,
                $binding->signalReversed,
                $wireOnValue,
                $wireOffValue,
            );

            $adapter->write($binding->channel, $newWireValue, $binding->config);
            $bindingResult['new_wire'] = $newWireValue;
        }
        unset($bindingResult);

        $first = $bindingResults[0];

        return [
            'binding' => $first['binding'],
            'current_locked' => $first['current_locked'],
            'new_locked' => $newLocked,
            'current_raw' => $first['current_raw'],
            'new_wire' => $first['new_wire'],
            'bindings' => $bindingResults,
        ];
    }

    /**
     * @return array{binding:ResolvedAccessBinding,new_active:bool,new_wire:mixed}|null
     */
    public function setReaderFeedbackState(Reader $reader, bool $targetActive): ?array
    {
        $binding = $this->bindingResolver->resolveReaderFeedbackBinding($reader);

        if ($binding === null) {
            return null;
        }

        $adapter = $this->outputAdapterRegistry->resolve($binding->adapterType);
        $wireOnValue = data_get($binding->config, 'signal.wire_on_value', data_get($binding->config, 'wire_on_value', 1));
        $wireOffValue = data_get($binding->config, 'signal.wire_off_value', data_get($binding->config, 'wire_off_value', 0));
        $newWireValue = SignalValueMapper::fromCanonicalBool(
            $targetActive,
            $binding->signalReversed,
            $wireOnValue,
            $wireOffValue,
        );

        $adapter->write($binding->channel, $newWireValue, $binding->config);

        return [
            'binding' => $binding,
            'new_active' => $targetActive,
            'new_wire' => $newWireValue,
        ];
    }

    public function readLockState(Reader $reader): ?bool
    {
        $binding = $this->bindingResolver->resolveLockPowerBinding($reader);

        if ($binding === null) {
            return null;
        }

        $adapter = $this->outputAdapterRegistry->resolve($binding->adapterType);
        $currentRaw = $adapter->read($binding->channel, $binding->config);

        return SignalValueMapper::toCanonicalBool($currentRaw, $binding->signalReversed);
    }
}
