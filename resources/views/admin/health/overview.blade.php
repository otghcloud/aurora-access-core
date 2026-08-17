@extends('layouts.admin')
@section('meta-page-title', 'Health Overview')
@section('page-title', 'Health Overview')
@section('page-pretitle', 'Diagnostics')

@section('content')
    @php($hasSerialDevicesRoute = \Illuminate\Support\Facades\Route::has('admin.serial-devices'))

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Health</h1>
            <p class="text-muted mb-0">Operational checks for queueing, supervisor, Redis, and MQTT retained state publishing.</p>
        </div>
        <form method="GET" action="{{ route('admin.health') }}" class="d-flex flex-wrap align-items-center gap-2">
            <label for="reader" class="form-label mb-0 small text-muted">Probe reader</label>
            <select name="reader" id="reader" class="form-select form-select-sm" style="min-width: 220px;">
                <option value="">Auto-select first reader</option>
                @foreach ($readers as $reader)
                    <option value="{{ $reader->identifier }}" @selected($probeReader === $reader->identifier)>
                        {{ $reader->name ?: $reader->identifier }}
                    </option>
                @endforeach
            </select>
            <label for="auto_refresh" class="form-label mb-0 small text-muted">Auto-refresh</label>
            <select name="auto_refresh" id="auto_refresh" class="form-select form-select-sm" style="min-width: 170px;">
                @foreach ($autoRefreshOptions as $interval)
                    <option value="{{ $interval }}" @selected($autoRefreshSeconds === $interval)>
                        {{ $interval === 0 ? 'Off' : 'Every '.$interval.'s' }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Run Health Check Now</button>
            @if ($autoRefreshSeconds > 0)
                <a href="{{ route('admin.health', ['reader' => $probeReader]) }}" class="btn btn-outline-secondary btn-sm">Stop Auto-refresh</a>
            @endif
        </form>
    </div>

    @if ($autoRefreshSeconds > 0)
        <div class="alert alert-info shadow-sm mb-4">
            Auto-refresh is enabled. This page reruns the full health check every {{ $autoRefreshSeconds }} seconds and refreshes the dashboard cache at the same time.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Overall</p>
                    <p class="display-6 mb-0 text-{{ $health['ok'] ? 'success' : 'danger' }}">{{ $health['ok'] ? 'OK' : 'FAIL' }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Critical Failures</p>
                    <p class="display-6 mb-0">{{ $health['critical_failures'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Warnings</p>
                    <p class="display-6 mb-0">{{ $health['warnings'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Generated</p>
                    <p class="mb-0 fw-semibold">{{ \Illuminate\Support\Carbon::parse($health['generated_at'])->format('d/m/Y H:i:s') }}</p>
                    <p class="small text-muted mb-0">queue={{ $health['queue_name'] }} redis={{ $health['redis_connection'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-0">Serial Devices</h2>
                <p class="text-muted small mb-0">Live serial monitor processes, device readability, and latest reader activity.</p>
            </div>
            @if ($hasSerialDevicesRoute)
                <a href="{{ route('admin.serial-devices') }}" class="btn btn-outline-primary btn-sm">Open Serial Devices</a>
            @else
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Serial Adapter Not Installed</button>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-1">Configured Readers</p>
                        <p class="display-6 mb-0">{{ $serialDiagnostics['readers_total'] ?? 0 }}</p>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-1">Running Monitors</p>
                        <p class="display-6 mb-0">{{ $serialDiagnostics['running_monitors'] ?? 0 }}</p>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-1">Readable Devices</p>
                        <p class="display-6 mb-0">{{ $serialDiagnostics['readable_devices'] ?? 0 }}</p>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-1">Reader Processes</p>
                        <p class="display-6 mb-0">{{ $serialDiagnostics['command_processes'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reader</th>
                            <th>Monitor</th>
                            <th>Device</th>
                            <th>Latest Event</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($serialDiagnostics['readers'] ?? []) as $reader)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $reader['name'] ?: $reader['identifier'] }}</div>
                                    <div class="small text-muted">{{ $reader['identifier'] }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ ($reader['process_running'] ?? false) ? 'success' : 'danger' }}">
                                        {{ ($reader['process_running'] ?? false) ? 'running' : 'not running' }}
                                    </span>
                                    <div class="small text-muted mt-1">supervisor={{ $reader['supervisor_status'] ?? 'unknown' }}</div>
                                </td>
                                <td>
                                    <code>{{ $reader['device'] ?? '/dev/'.$reader['identifier'] }}</code>
                                    <div class="small text-muted mt-1">{{ ($reader['device_readable'] ?? false) ? 'readable' : 'not readable' }}</div>
                                </td>
                                <td>
                                    <div>{{ $reader['latest_event_status'] ?? 'No events yet' }}</div>
                                    <div class="small text-muted">{{ ! empty($reader['latest_event_at']) ? \Illuminate\Support\Carbon::parse($reader['latest_event_at'])->format('d/m/Y H:i:s') : '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">No access readers configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">MQTT Drift Sync</h2>
        </div>
        <div class="card-body">
            @if (is_array($health['mqtt_sync'] ?? null))
                <div class="row g-3">
                    <div class="col-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1">Last Run</p>
                            <p class="mb-0 fw-semibold">{{ \Illuminate\Support\Carbon::parse($health['mqtt_sync']['generated_at'])->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1">Readers Checked</p>
                            <p class="display-6 mb-0">{{ $health['mqtt_sync']['readers_checked'] ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1">Drift Detected</p>
                            <p class="display-6 mb-0">{{ $health['mqtt_sync']['drift_detected'] ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded p-3 h-100">
                            <p class="text-muted mb-1">Republished</p>
                            <p class="display-6 mb-0">{{ $health['mqtt_sync']['republished'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
                <p class="small text-muted mt-3 mb-0">
                    failures={{ $health['mqtt_sync']['failures'] ?? 0 }}
                    dry_run={{ ! empty($health['mqtt_sync']['dry_run']) ? 'yes' : 'no' }}
                </p>
            @else
                <p class="text-muted mb-0">No reconciliation run has been recorded yet.</p>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Check Results</h2>
            <span class="badge text-bg-secondary">{{ count($health['checks']) }} checks</span>
        </div>
        <div class="accordion accordion-flush" id="health-check-groups">
            @foreach (($health['checks_by_type'] ?? []) as $type => $checks)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="health-check-heading-{{ $loop->index }}">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#health-check-collapse-{{ $loop->index }}" aria-expanded="true" aria-controls="health-check-collapse-{{ $loop->index }}">
                            {{ $type }}
                            <span class="badge text-bg-secondary ms-2">{{ count($checks) }}</span>
                        </button>
                    </h3>
                    <div id="health-check-collapse-{{ $loop->index }}" class="accordion-collapse collapse show" aria-labelledby="health-check-heading-{{ $loop->index }}">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Check</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($checks as $check)
                                            <tr>
                                                <td class="fw-semibold">{{ $check['name'] }}</td>
                                                <td>
                                                    <span class="badge text-bg-{{ $check['status'] === 'PASS' ? 'success' : ($check['status'] === 'WARN' ? 'warning' : 'danger') }}">
                                                        {{ $check['status'] }}
                                                    </span>
                                                </td>
                                                <td class="small">{{ $check['details'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($autoRefreshSeconds > 0)
        <script>
            window.setTimeout(function () {
                window.location.reload();
            }, {{ $autoRefreshSeconds * 1000 }});
        </script>
    @endif
@endsection