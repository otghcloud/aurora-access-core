@extends('layouts.admin')
@section('meta-page-title', 'Access Cards')
@section('page-title', 'Access Credentials')
@section('page-pretitle', 'Access')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Cards</h1>
        <a href="{{ route('admin.access-cards.create') }}" class="btn btn-primary">New Card</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Card Number</th>
                        <th scope="col">User</th>
                        <th scope="col">Active</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessCards as $accessCard)
                        <tr>
                            <td>{{ $accessCard->card_number }}</td>
                            <td>{{ $accessCard->user?->name ?? '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $accessCard->active ? 'success' : 'secondary' }}">
                                    {{ $accessCard->active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $accessCard->description ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-cards.edit', $accessCard) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-cards.destroy', $accessCard) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this card?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No access cards created yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessCards->links() }}
    </div>
@endsection
