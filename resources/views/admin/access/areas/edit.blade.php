@extends('layouts.admin')
@section('meta-page-title', 'Edit Area')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Area</h1>
        <a href="{{ route('admin.access-areas.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-areas.update', $accessArea) }}">
                @csrf
                @method('PUT')
                @include('admin.access.areas._form', ['accessArea' => $accessArea])
                <button type="submit" class="btn btn-primary mt-4">Update Area</button>
            </form>
        </div>
    </div>
@endsection
