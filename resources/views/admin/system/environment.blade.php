@extends('layouts.admin')
@section('meta-page-title', 'Environment')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Environment</h1>
            <p class="text-muted mb-0">Installed access packages and currently registered configuration sections.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Installed Access Packages</h2>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Version</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($installedAccessPackages as $pkg)
                                <tr>
                                    <td><code>{{ $pkg['name'] }}</code></td>
                                    <td>{{ $pkg['version'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center py-4 text-muted">No access packages detected.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Registered Config Sections</h2>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($registeredConfigSections as $section)
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">{{ $section['section_label'] }}</div>
                                @if (! empty($section['package']))
                                    <div class="small text-muted">{{ $section['package'] }}</div>
                                @endif
                            </div>
                            <span class="badge text-bg-secondary">{{ count($section['fields'] ?? []) }} fields</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No config sections registered.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
