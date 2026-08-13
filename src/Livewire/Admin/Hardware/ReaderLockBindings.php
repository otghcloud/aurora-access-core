<?php

namespace OTGH\AccessControl\Core\Livewire\Admin\Hardware;

use Livewire\Attributes\Computed;
use Livewire\Component;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use OTGH\AccessControl\Core\Models\Hardware\ReaderLockBinding;

class ReaderLockBindings extends Component
{
    public Reader $reader;

    #[Computed]
    public function boundLocks()
    {
        return $this->reader->lockBindings()
            ->with('lock')
            ->orderBy('lock_id')
            ->get();
    }

    #[Computed]
    public function availableLocks()
    {
        // Get all locks except those already bound
        $boundLockIds = $this->boundLocks->pluck('lock_id')->all();

        return Lock::query()
            ->whereNotIn('id', $boundLockIds)
            ->orderBy('area_id')
            ->orderBy('name')
            ->get();
    }

    public function addBinding(int $lockId): void
    {
        $lock = Lock::find($lockId);

        if (! $lock) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Lock not found']);

            return;
        }

        ReaderLockBinding::firstOrCreate(
            [
                'reader_id' => $this->reader->id,
                'lock_id' => $lockId,
            ],
            [
                'area_id' => $lock->area_id,
                'action_type' => 1, // LOCK
                'enabled' => true,
            ]
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => "Lock '{$lock->name}' bound to reader"]);
        $this->resetComputedProperties();
    }

    public function removeBinding(int $bindingId): void
    {
        $binding = ReaderLockBinding::find($bindingId);

        if (! $binding) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Binding not found']);

            return;
        }

        $lockName = $binding->lock->name;
        $binding->delete();

        $this->dispatch('notify', ['type' => 'success', 'message' => "Lock '{$lockName}' unbound from reader"]);
        $this->resetComputedProperties();
    }

    public function toggleEnabled(int $bindingId): void
    {
        $binding = ReaderLockBinding::find($bindingId);

        if (! $binding) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Binding not found']);

            return;
        }

        $binding->update(['enabled' => ! $binding->enabled]);

        $status = $binding->enabled ? 'enabled' : 'disabled';
        $this->dispatch('notify', ['type' => 'success', 'message' => "Binding {$status}"]);
        $this->resetComputedProperties();
    }

    public function render()
    {
        return view('livewire.admin.hardware.reader-lock-bindings', [
            'boundLocks' => $this->boundLocks,
            'availableLocks' => $this->availableLocks,
        ]);
    }
}
