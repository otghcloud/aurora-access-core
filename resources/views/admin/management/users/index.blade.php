@extends('layouts.admin')
@section('meta-page-title', 'System Users')
@section('page-title', 'System Users')
@section('page-pretitle', 'Administration')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">System Users</h1>
        <a href="{{ route('admin.system-users.create') }}" class="btn btn-primary">New System User</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">API Tokens</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $systemUser)
                        <tr>
                            <td>{{ $systemUser->name }}</td>
                            <td>{{ $systemUser->email }}</td>
                            <td>{{ $systemUser->tokens_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.system-users.edit', $systemUser) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.system-users.destroy', $systemUser) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this system user?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No system users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
@endsection
