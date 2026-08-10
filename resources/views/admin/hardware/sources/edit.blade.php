@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Access Source</h1>
        <a href="{{ route('admin.access-sources.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-sources.update', $accessSource) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.sources._form', ['accessSource' => $accessSource])
                <button type="submit" class="btn btn-primary mt-4">Update Source</button>
            </form>
        </div>
    </div>
@endsection
