@extends('layouts.admin')
@section('meta-page-title', 'Access Sources')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Sources</h1>
        <a href="{{ route('admin.access-sources.create') }}" class="btn btn-primary">New Source</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Identifier</th>
                        <th scope="col">Type</th>
                        <th scope="col">Endpoint</th>
                        <th scope="col">Enabled</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessSources as $accessSource)
                        <tr>
                            <td>{{ $accessSource->name }}</td>
                            <td><code>{{ $accessSource->identifier }}</code></td>
                            <td><span class="badge text-bg-info text-uppercase">{{ $accessSource->type }}</span></td>
                            <td><code>{{ $accessSource->endpoint ?? '-' }}</code></td>
                            <td>
                                <span class="badge text-bg-{{ $accessSource->enabled ? 'success' : 'secondary' }}">
                                    {{ $accessSource->enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.access-sources.test', $accessSource) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Test</button>
                                </form>
                                <a href="{{ route('admin.access-sources.edit', $accessSource) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-sources.destroy', $accessSource) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this source?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No sources configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessSources->links() }}
    </div>
@endsection
