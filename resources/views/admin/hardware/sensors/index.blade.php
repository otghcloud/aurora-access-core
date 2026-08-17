@extends('layouts.admin')
@section('meta-page-title', 'Sensors')
@section('page-title', 'Sensors')
@section('page-pretitle', 'Hardware')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Sensors</h1>
        <a href="{{ route('admin.access-sensors.create') }}" class="btn btn-primary">New Sensor</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Identifier</th>
                        <th scope="col">Area</th>
                        <th scope="col">State</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accessSensors as $accessSensor)
                        <tr>
                            <td>{{ $accessSensor->name }}</td>
                            <td><code>{{ $accessSensor->identifier }}</code></td>
                            <td>{{ $accessSensor->area?->name ?? '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $accessSensor->state ? 'success' : 'secondary' }}">
                                    {{ $accessSensor->state ? 'Open' : 'Closed' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.access-sensors.show', $accessSensor) }}" class="btn btn-sm btn-outline-secondary">Details</a>
                                <a href="{{ route('admin.access-sensors.edit', $accessSensor) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.access-sensors.destroy', $accessSensor) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this sensor?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No sensors configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $accessSensors->links() }}
    </div>
@endsection
