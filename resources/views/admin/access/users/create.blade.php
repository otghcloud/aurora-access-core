@extends('layouts.admin')
@section('meta-page-title', 'Create Access User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Access User</h1>
        <a href="{{ route('admin.access-users.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-users.store') }}">
                @csrf
                @include('admin.access.users._form')
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>
@endsection
