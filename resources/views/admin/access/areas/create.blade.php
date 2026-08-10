@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Area</h1>
        <a href="{{ route('admin.access-areas.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-areas.store') }}">
                @csrf
                @include('admin.access.areas._form', ['accessArea' => null])
                <button type="submit" class="btn btn-primary mt-4">Create Area</button>
            </form>
        </div>
    </div>
@endsection
