@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Lock</h1>
        <a href="{{ route('admin.access-locks.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-locks.store') }}">
                @csrf
                @include('admin.hardware.locks._form', ['accessLock' => null])
                <button type="submit" class="btn btn-primary mt-4">Create Lock</button>
            </form>
        </div>
    </div>
@endsection
