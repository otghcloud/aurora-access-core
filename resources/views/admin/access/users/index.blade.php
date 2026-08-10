@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Users</h1>
        <a href="{{ route('admin.access-users.create') }}" class="btn btn-primary">New User</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Cards</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessUsers as $accessUser)
                        <tr>
                            <td>{{ $accessUser->name }}</td>
                            <td>{{ $accessUser->cards_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-users.edit', $accessUser) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-users.destroy', $accessUser) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No access users created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessUsers->links() }}
    </div>
@endsection
