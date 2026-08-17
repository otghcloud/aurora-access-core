@extends('layouts.admin')
@section('meta-page-title', 'Access Switches')
@section('page-title', 'Switches')
@section('page-pretitle', 'Hardware')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Switches</h1>
        <a href="{{ route('admin.access-switches.create') }}" class="btn btn-primary">New Switch</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Identifier</th>
                        <th scope="col">Area</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessSwitches as $accessSwitch)
                        <tr>
                            <td>{{ $accessSwitch->name }}</td>
                            <td><code>{{ $accessSwitch->identifier }}</code></td>
                            <td>{{ $accessSwitch->area?->name ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-switches.edit', $accessSwitch) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-switches.destroy', $accessSwitch) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this switch?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No switches configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessSwitches->links() }}
    </div>
@endsection
