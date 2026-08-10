<?php

namespace App\Services\AccessControl;

use App\Enums\AccessControl\AccessBindingActionKey;
use App\Models\Hardware\AdapterBinding;
use App\Models\Hardware\Reader;
use Illuminate\Support\Facades\Schema;

class AccessBindingResolver
{
    /**
     * @return array<int,ResolvedAccessBinding>
     */
    public function resolveLockPowerBindings(Reader $reader): array
    {
        if ($this->bindingsTableExists()) {
            $bindings = [];

            $area = $reader->area;
            $roomLockIds = $area?->locks()->pluck('id')->all() ?? [];

            if ($roomLockIds !== []) {
                $lockBindings = AdapterBinding::query()
                    ->with('source')
                    ->where('direction', 'output')
                    ->where('enabled', true)
                    ->where('target_type', 'lock')
                    ->whereIn('target_id', $roomLockIds)
                    ->whereIn('action_key', AccessBindingActionKey::queryCandidatesFor([
                        AccessBindingActionKey::LOCK_POWER,
                    ]))
                    ->orderBy('id')
                    ->get();

                foreach ($lockBindings as $lockBinding) {
                    $bindings[] = $this->fromModel($lockBinding);
                }
            }

            $readerBinding = AdapterBinding::query()
                ->with('source')
                ->where('direction', 'output')
                ->where('enabled', true)
                ->where('target_type', 'reader')
                ->where('target_id', $reader->id)
                ->whereIn('action_key', AccessBindingActionKey::queryCandidatesFor([
                    AccessBindingActionKey::LOCK_POWER,
                ]))
                ->latest('id')
                ->first();

            if ($readerBinding !== null) {
                $bindings[] = $this->fromModel($readerBinding);
            }

            $unique = [];

            foreach ($bindings as $binding) {
                $signature = implode('|', [
                    $binding->adapterType,
                    $binding->actionKey,
                    $binding->channel,
                    $binding->signalReversed ? '1' : '0',
                    json_encode($binding->config, JSON_UNESCAPED_SLASHES),
                ]);

                $unique[$signature] = $binding;
            }

            if ($unique !== []) {
                return array_values($unique);
            }
        }

        return [];
    }

    public function resolveLockPowerBinding(Reader $reader): ?ResolvedAccessBinding
    {
        $bindings = $this->resolveLockPowerBindings($reader);

        return $bindings[0] ?? null;
    }

    public function resolveReaderFeedbackBinding(Reader $reader): ?ResolvedAccessBinding
    {
        if ($this->bindingsTableExists()) {
            $binding = AdapterBinding::query()
                ->with('source')
                ->where('direction', 'output')
                ->where('enabled', true)
                ->where('target_type', 'reader')
                ->where('target_id', $reader->id)
                ->whereIn('action_key', AccessBindingActionKey::queryCandidatesFor([
                    AccessBindingActionKey::READER_FEEDBACK_STATE,
                ]))
                ->latest('id')
                ->first();

            if ($binding !== null) {
                return $this->fromModel($binding);
            }
        }

        return null;
    }

    private function bindingsTableExists(): bool
    {
        return Schema::hasTable('adapter_bindings');
    }

    private function fromModel(AdapterBinding $binding): ResolvedAccessBinding
    {
        $sourceConfig = is_array($binding->source?->config) ? $binding->source->config : [];
        $bindingConfig = is_array($binding->config) ? $binding->config : [];

        return new ResolvedAccessBinding(
            adapterType: (string) $binding->adapter_type,
            actionKey: AccessBindingActionKey::keyFor($binding->action_key) ?? (string) $binding->action_key,
            channel: (string) $binding->channel,
            signalReversed: (bool) $binding->signal_reversed,
            config: array_replace_recursive($sourceConfig, $bindingConfig),
            metadata: is_array($binding->metadata) ? $binding->metadata : [],
        );
    }
}
