@extends('layouts.admin')
@section('meta-page-title', 'Edit Access Reader')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Access Reader</h1>
        <div>
            <a href="{{ route('admin.access-readers.lock-bindings.edit', $accessReader) }}" class="btn btn-outline-info btn-sm">Lock Bindings</a>
            <a href="{{ route('admin.access-readers.bindings.edit', $accessReader) }}" class="btn btn-outline-info btn-sm">Adapter Bindings</a>
            <a href="{{ route('admin.access-readers.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-readers.update', $accessReader) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.readers._form', ['accessReader' => $accessReader])
                <button type="submit" class="btn btn-primary mt-4">Update Reader</button>
            </form>
        </div>
    </div>
@endsection
