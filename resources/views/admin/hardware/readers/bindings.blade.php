@extends('layouts.admin')
@section('meta-page-title', 'Reader Bindings: ' . $accessReader->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Reader Bindings: {{ $accessReader->name }}</h1>
            <div class="text-muted">Identifier: {{ $accessReader->identifier }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.access-readers.show', $accessReader) }}" class="btn btn-outline-secondary">Reader Details</a>
            <a href="{{ route('admin.access-readers.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-readers.bindings.update', $accessReader) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.readers._bindings_form')
                <button type="submit" class="btn btn-primary mt-4">Save Reader Bindings</button>
            </form>
        </div>
    </div>
@endsection
