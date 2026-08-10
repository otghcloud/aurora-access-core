<div wire:poll.3s>
    @if ($statusMessage)
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ $statusMessage }}
            <button type="button" class="btn-close" aria-label="Close" wire:click="$set('statusMessage', null)"></button>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @forelse ($lockCards as $lockCard)
            @php
                $reader = $lockCard['reader'];
                $status = $lockCard['status'] ?? ['state' => 'unknown', 'label' => 'Unknown', 'badge' => 'secondary', 'error' => null];
                $isKnown = in_array($status['state'] ?? 'unknown', ['locked', 'unlocked'], true);
                $isLocked = ($status['state'] ?? null) === 'locked';
                $autolockEnabled = (bool) ($lockCard['autolock_enabled'] ?? false);
                $primaryLock = $lockCard['primary_lock'] ?? null;
                $expectedKnown = in_array($lockCard['expected_lock_power'] ?? null, [0, 1], true);
                $matchesExpected = $lockCard['state_matches_expected'] ?? null;
            @endphp
            <div class="col-12 col-md-6 col-xl-4" wire:key="dashboard-lock-card-{{ $reader->id }}">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <h3 class="h5 mb-1">{{ $reader->area?->name ?? 'Unassigned Area' }}</h3>
                            </div>
                            <span class="badge text-bg-{{ $status['badge'] ?? 'secondary' }} fs-6">{{ $status['label'] ?? 'Unknown' }}</span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="small text-muted">
                                <strong>Adapter:</strong> {{ strtoupper((string) ($status['adapter_type'] ?? '-')) }}
                            </div>
                            @if (($status['signal_reversed'] ?? false) === true)
                                <span class="badge text-bg-warning">Reversed</span>
                            @endif
                            @if ($expectedKnown)
                                <span class="badge text-bg-{{ ($matchesExpected === false) ? 'warning' : 'secondary' }}">Expected {{ $lockCard['expected_lock_label'] }}</span>
                            @else
                                <span class="badge text-bg-secondary">Expected Unknown</span>
                            @endif
                            @if ($matchesExpected === false)
                                <span class="badge text-bg-danger">Drift</span>
                            @elseif ($matchesExpected === true)
                                <span class="badge text-bg-success">In Sync</span>
                            @endif
                        </div>

                        <div class="small text-muted d-flex flex-column gap-1">
                            <div><strong>Stored state:</strong> {{ $lockCard['expected_lock_label'] ?? 'Unknown' }}</div>
                            @if (! empty($lockCard['expected_lock_updated_at']))
                                <div><strong>Updated:</strong> {{ \Illuminate\Support\Carbon::parse($lockCard['expected_lock_updated_at'])->format('d/m/Y H:i:s') }}</div>
                            @endif
                            @if (! empty($lockCard['expected_lock_source']))
                                <div><strong>Source:</strong> {{ $lockCard['expected_lock_source'] }}</div>
                            @endif
                        </div>

                        <div class="mt-auto d-flex flex-wrap align-items-center gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-{{ $autolockEnabled ? 'success' : 'secondary' }}"
                                wire:click="toggleAutolock({{ $reader->id }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleAutolock({{ $reader->id }})"
                                title="Click to toggle autolock"
                            >
                                <span wire:loading.remove wire:target="toggleAutolock({{ $reader->id }})">
                                    {{ $autolockEnabled ? 'Enabled' : 'Disabled' }}
                                </span>
                                <span wire:loading wire:target="toggleAutolock({{ $reader->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Saving...
                                </span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-{{ $isLocked ? 'outline-warning' : 'outline-success' }}"
                                wire:click="toggleLock({{ $reader->id }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleLock({{ $reader->id }})"
                                @disabled(! $isKnown)
                            >
                                <span wire:loading.remove wire:target="toggleLock({{ $reader->id }})">
                                    {{ $isLocked ? 'Unlock' : 'Lock' }}
                                </span>
                                <span wire:loading wire:target="toggleLock({{ $reader->id }})">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Sending...
                                </span>
                            </button>
                            @if ($primaryLock)
                                <a href="{{ route('admin.access-locks.show', $primaryLock) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                            @endif
                            <span class="small text-muted">
                                Autolock: {{ $lockCard['autolock_duration'] }}s
                                ({{ ($lockCard['autolock_source'] ?? 'area_default') === 'lock_override' ? 'Override' : 'Area Default' }})
                            </span>
                        </div>

                        @if (! empty($status['error']))
                            <div class="small text-muted" title="{{ $status['error'] }}">Status check failed</div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5 text-muted">
                        No configured locks yet.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
