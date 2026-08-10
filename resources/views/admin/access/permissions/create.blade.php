@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Area Permission</h1>
        <a href="{{ route('admin.access-area-permissions.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-area-permissions.store') }}">
                @csrf
                @include('admin.access.permissions._form', ['accessAreaPermission' => null])
                <button type="submit" class="btn btn-primary mt-4">Create Permission</button>
            </form>
        </div>
    </div>
@endsection
