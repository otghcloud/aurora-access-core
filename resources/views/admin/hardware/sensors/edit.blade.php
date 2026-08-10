@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Sensor</h1>
        <a href="{{ route('admin.access-sensors.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-sensors.update', $accessSensor) }}">
                @csrf
                @method('PUT')
                @include('admin.hardware.sensors._form', ['accessSensor' => $accessSensor])
                <button type="submit" class="btn btn-primary mt-4">Save Sensor</button>
            </form>
        </div>
    </div>
@endsection
