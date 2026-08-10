@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create System User</h1>
        <a href="{{ route('admin.system-users.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.system-users.store') }}">
                @csrf
                @include('admin.management.users._form', ['systemUser' => null])
                <button type="submit" class="btn btn-primary mt-4">Create User</button>
            </form>
        </div>
    </div>
@endsection
