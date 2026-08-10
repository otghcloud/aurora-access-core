<?php

namespace App\Livewire\Admin;

use App\Models\Hardware\Reader;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class AccessReadersTable extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Computed]
    public function readers()
    {
        return Reader::query()->with('area')->latest('id')->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.access-readers-table', [
            'accessReaders' => $this->readers,
        ]);
    }
}
