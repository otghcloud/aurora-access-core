@extends('layouts.admin')
@section('meta-page-title', 'Edit Access User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Access User</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-area-permissions.index', ['individual_id' => $accessUser->id]) }}" class="btn btn-outline-primary">Manage Area Permissions</a>
            <a href="{{ route('admin.access-users.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-users.update', $accessUser) }}">
                @csrf
                @method('PUT')
                @include('admin.access.users._form', ['accessUser' => $accessUser])
                <button type="submit" class="btn btn-primary">Update User</button>
            </form>
        </div>
    </div>
@endsection
