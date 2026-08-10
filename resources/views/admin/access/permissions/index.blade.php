@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Area Permissions</h1>
        <a href="{{ route('admin.access-area-permissions.create') }}" class="btn btn-primary">New Permission</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.access-area-permissions.index') }}" class="row g-2">
                <div class="col-md-4">
                    <select class="form-select" name="individual_id">
                        <option value="">All Users</option>
                        @foreach ($accessUsers as $user)
                            <option value="{{ $user->id }}" @selected((string) request('individual_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="area_id">
                        <option value="">All Areas</option>
                        @foreach ($accessAreas as $area)
                            <option value="{{ $area->id }}" @selected((string) request('area_id') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="permission">
                        <option value="">Allow + Deny</option>
                        <option value="allow" @selected(request('permission') === 'allow')>Allow</option>
                        <option value="deny" @selected(request('permission') === 'deny')>Deny</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Area</th>
                        <th>Permission</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td>{{ $permission->accessUser?->name ?? '-' }}</td>
                            <td>{{ $permission->area?->name ?? '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $permission->permission === 'allow' ? 'success' : 'danger' }}">{{ strtoupper($permission->permission) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-area-permissions.edit', $permission) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-area-permissions.destroy', $permission) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this permission?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No area permissions configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $permissions->links() }}
    </div>
@endsection
