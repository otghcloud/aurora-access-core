@extends('layouts.admin-datatable')
@section('meta-page-title', 'Area Permissions')
@section('page-title', 'Area Permissions')
@section('page-pretitle', 'Access')

@section('page-extra')
    <form method="GET" action="{{ route('admin.access-area-permissions.index') }}" class="row g-2">
        <div class="col-md-4"><select class="form-select" name="individual_id"><option value="">All Users</option>@foreach ($accessUsers as $user)<option value="{{ $user->id }}" @selected((string) request('individual_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><select class="form-select" name="area_id"><option value="">All Areas</option>@foreach ($accessAreas as $area)<option value="{{ $area->id }}" @selected((string) request('area_id') === (string) $area->id)>{{ $area->name }} ({{ $area->identifier }})</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="permission"><option value="">Allow + Deny</option><option value="allow" @selected(request('permission') === 'allow')>Allow</option><option value="deny" @selected(request('permission') === 'deny')>Deny</option></select></div>
        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
    </form>
@endsection

@section('page-actions')
    <a href="{{ route('admin.access-area-permissions.create') }}" class="btn btn-primary">New Permission</a>
@endsection
