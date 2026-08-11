@extends('layouts.admin')
@section('meta-page-title', 'Edit Area Permission')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Area Permission</h1>
        <a href="{{ route('admin.access-area-permissions.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-area-permissions.update', $accessAreaPermission) }}">
                @csrf
                @method('PUT')
                @include('admin.access.permissions._form', ['accessAreaPermission' => $accessAreaPermission])
                <button type="submit" class="btn btn-primary mt-4">Update Permission</button>
            </form>
        </div>
    </div>
@endsection
