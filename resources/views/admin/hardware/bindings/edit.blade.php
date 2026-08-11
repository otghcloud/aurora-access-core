@extends('layouts.admin')
@section('meta-page-title', 'Edit Binding')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Binding</h1>
        <a href="{{ route('admin.access-bindings.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-bindings.update', $accessBinding) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.bindings._form', ['accessBinding' => $accessBinding])
                <button type="submit" class="btn btn-primary mt-4">Update Binding</button>
            </form>
        </div>
    </div>
@endsection
