@extends('layouts.admin')
@section('meta-page-title', 'Locks')
@section('page-title', 'Locks')
@section('page-pretitle', 'Hardware')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Locks</h1>
        <a href="{{ route('admin.access-locks.create') }}" class="btn btn-primary">New Lock</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Identifier</th>
                        <th>Area</th>
                        <th>Role</th>
                        <th>Auto-lock Config</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessLocks as $accessLock)
                        <tr>
                            <td class="fw-semibold">{{ $accessLock->name }}</td>
                            <td><code>{{ $accessLock->identifier }}</code></td>
                            <td>{{ $accessLock->area?->name ?? 'Unassigned' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $accessLock->is_primary ? 'success' : 'secondary' }}">
                                    {{ $accessLock->is_primary ? 'Primary' : 'Secondary' }}
                                </span>
                            </td>
                            <td>
                                @if ($accessLock->usesAutolockOverride())
                                    <span class="badge text-bg-warning">Override</span>
                                @else
                                    <span class="badge text-bg-secondary">Inherit Area</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.access-locks.show', $accessLock) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                                    <a href="{{ route('admin.access-locks.edit', $accessLock) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.access-locks.destroy', $accessLock) }}" onsubmit="return confirm('Delete this lock?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No locks configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessLocks->links() }}
    </div>
@endsection
