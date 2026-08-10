@extends('layouts.admin')

@section('content')
    @php
        $config = $accessReader->config ?? [];
        $metadata = $accessReader->metadata ?? [];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Reader {{ $accessReader->name }}</h1>
            <div class="text-muted">Identifier: {{ $accessReader->identifier }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-readers.edit', $accessReader) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('admin.access-readers.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Configuration Snapshot</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h3 class="h6">Serial Wiegand</h3>
                            <div><strong>Device:</strong> <code>{{ data_get($config, 'wiegand.device', '/dev/'.$accessReader->identifier) }}</code></div>
                            <div><strong>Baud Rate:</strong> {{ data_get($config, 'wiegand.baud_rate', 9600) }}</div>
                            <div><strong>Read Timeout:</strong> {{ data_get($config, 'wiegand.timeout', 1) }}s</div>
                            <div><strong>Duplicate Window:</strong> {{ data_get($config, 'wiegand.duplicate_window', 2) }}s</div>
                            <div><strong>Doorbell Dedup:</strong> {{ data_get($config, 'wiegand.doorbell_duplicate_window', 2) }}s</div>
                            <div><strong>Keypad Timeout:</strong> {{ data_get($config, 'wiegand.keypad_timeout', 3) }}s</div>
                            <div><strong>Card Min Value:</strong> {{ data_get($config, 'wiegand.card_min_value', 15) }}</div>
                            <div><strong>Doorbell Value:</strong> {{ data_get($config, 'wiegand.doorbell_value', 11) }}</div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6">Metadata</h3>
                            <div><strong>Input Format:</strong> {{ strtoupper((string) data_get($config, 'general.input_format', 'wiegand')) }}</div>
                            <div><strong>Reader:</strong> {{ data_get($metadata, 'reader.model', '-') }} / {{ data_get($metadata, 'reader.type', '-') }}</div>
                            <div><strong>Lock:</strong> {{ data_get($metadata, 'lock.model', '-') }} / {{ data_get($metadata, 'lock.type', '-') }}</div>
                            <div><strong>Area:</strong> {{ $accessReader->area?->name ?? '-' }}</div>
                            <div><strong>Primary Lock ID:</strong> {{ $primaryLockId ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Reader Output Bindings</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Adapter</th>
                                <th>Channel</th>
                                <th>Reversed</th>
                                <th>Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($readerBindings as $binding)
                                <tr>
                                    @php($actionEnum = \App\Enums\AccessControl\AccessBindingActionKey::fromStored($binding->action_key))
                                    <td><code>{{ $actionEnum?->key() ?? $binding->action_key }}</code></td>
                                    <td><code>{{ $binding->adapter_type }}</code></td>
                                    <td><code>{{ $binding->channel ?? '-' }}</code></td>
                                    <td>{{ $binding->signal_reversed ? 'Yes' : 'No' }}</td>
                                    <td>{{ $binding->enabled ? 'Yes' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">No reader-level bindings.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Recent Activity For This Reader</h2>
            <span class="text-muted small">Last {{ $recentEvents->count() }} events</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Status</th>
                        <th scope="col">Granted</th>
                        <th scope="col">Card</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentEvents as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td><span class="badge text-bg-{{ $event->granted ? 'success' : 'secondary' }}">{{ $event->status_label }}</span></td>
                            <td>{{ $event->granted ? 'Yes' : 'No' }}</td>
                            <td>
                                @include('admin.components.card-display', [
                                    'accessCard' => $event->accessCard,
                                    'cardNumber' => $event->card_number,
                                ])
                            </td>
                            <td>{{ $event->reason ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.access-events.show', $event) }}" class="btn btn-sm btn-outline-secondary">Event</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No events for this reader yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
