@extends('layouts.admin')
@section('meta-page-title', 'Create Access Reader')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Access Reader</h1>
        <a href="{{ route('admin.access-readers.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-readers.store') }}">
                @csrf
                @include('admin.hardware.readers._form', ['accessReader' => null])
                <button type="submit" class="btn btn-primary mt-4">Create Reader</button>
            </form>
        </div>
    </div>
@endsection
