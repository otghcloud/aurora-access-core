<div>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Identifier</th>
                        <th scope="col">Area</th>
                        <th scope="col">Metadata</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessReaders as $accessReader)
                        <tr wire:key="reader-row-{{ $accessReader->id }}-{{ $accessReaders->currentPage() }}">
                            <td>{{ $accessReader->name }}</td>
                            <td>{{ $accessReader->identifier }}</td>
                            <td>{{ $accessReader->area?->name ?? 'Unassigned' }}</td>
                            <td>
                                <div><strong>Reader:</strong> {{ data_get($accessReader->metadata, 'reader.model', '-') }} / {{ data_get($accessReader->metadata, 'reader.type', '-') }}</div>
                                <div><strong>Lock:</strong> {{ data_get($accessReader->metadata, 'lock.model', '-') }} / {{ data_get($accessReader->metadata, 'lock.type', '-') }}</div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-readers.show', $accessReader) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('admin.access-readers.edit', $accessReader) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-readers.destroy', $accessReader) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this reader?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No readers configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessReaders->links() }}
    </div>
</div>
