@extends('layouts.admin')
@section('meta-page-title', 'Edit Access Switch ' . $accessSwitch->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Switch</h1>
        <a href="{{ route('admin.access-switches.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-switches.update', $accessSwitch) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.switches._form', ['accessSwitch' => $accessSwitch])
                <button type="submit" class="btn btn-primary mt-4">Update Switch</button>
            </form>
        </div>
    </div>
@endsection
