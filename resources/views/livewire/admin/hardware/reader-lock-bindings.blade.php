<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">Lock Bindings</h5>
        <small class="text-muted">Configure which locks this reader controls</small>
    </div>
    <div class="card-body">
        <!-- Bound Locks Section -->
        <div class="mb-4">
            <h6>Bound Locks</h6>
            @if ($boundLocks->isEmpty())
                <p class="text-muted mb-3">No locks bound to this reader yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Lock Name</th>
                                <th>Area</th>
                                <th>Identifier</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($boundLocks as $binding)
                                <tr wire:key="binding-{{ $binding->id }}">
                                    <td>{{ $binding->lock->name }}</td>
                                    <td>{{ $binding->area->name }}</td>
                                    <td><code>{{ $binding->lock->identifier }}</code></td>
                                    <td>
                                        @if ($binding->enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-secondary">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-warning"
                                            wire:click="toggleEnabled({{ $binding->id }})"
                                            title="{{ $binding->enabled ? 'Disable' : 'Enable' }}"
                                        >
                                            @if ($binding->enabled)
                                                <i class="fas fa-lock-open"></i> Disable
                                            @else
                                                <i class="fas fa-lock"></i> Enable
                                            @endif
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger"
                                            wire:click="removeBinding({{ $binding->id }})"
                                            onclick="return confirm('Remove this binding?')"
                                        >
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Add New Binding Section -->
        @if ($availableLocks->isNotEmpty())
            <hr>
            <div>
                <h6>Add Lock Binding</h6>
                <div class="row g-2">
                    <div class="col-md-8">
                        <div class="list-group" style="max-height: 250px; overflow-y: auto;">
                            @foreach ($availableLocks as $lock)
                                <button
                                    type="button"
                                    class="list-group-item list-group-item-action text-start"
                                    wire:click="addBinding({{ $lock->id }})"
                                    wire:key="available-lock-{{ $lock->id }}"
                                >
                                    <div class="d-flex w-100 justify-content-between">
                                        <strong>{{ $lock->name }}</strong>
                                        <small class="text-muted">{{ $lock->area->name }}</small>
                                    </div>
                                    <small class="text-muted"><code>{{ $lock->identifier }}</code></small>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
