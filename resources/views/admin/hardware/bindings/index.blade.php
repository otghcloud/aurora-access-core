@extends('layouts.admin-datatable')
@section('meta-page-title', 'Bindings')
@section('page-title', 'Bindings')
@section('page-pretitle', 'Hardware')

@section('page-extra')
    <form method="GET" action="{{ route('admin.access-bindings.index') }}" class="row g-2">
        <div class="col-md-2"><select class="form-select" name="direction"><option value="">All Directions</option><option value="input" @selected(request('direction') === 'input')>Input</option><option value="output" @selected(request('direction') === 'output')>Output</option></select></div>
        <div class="col-md-2"><select class="form-select" name="adapter_type"><option value="">All Adapters</option>@foreach (($adapterTypeOptions ?? []) as $adapter)<option value="{{ $adapter['value'] }}" @selected(request('adapter_type') === $adapter['value'])>{{ $adapter['label'] }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="target_type"><option value="">All Targets</option>@foreach (['reader' => 'Reader', 'lock' => 'Lock', 'area' => 'Area', 'switch' => 'Switch', 'sensor' => 'Sensor'] as $value => $label)<option value="{{ $value }}" @selected(request('target_type') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="action_key"><option value="">All Actions</option>@foreach (($actionOptions ?? []) as $option)<option value="{{ $option['value'] }}" @selected((string) request('action_key') === (string) $option['value'])>{{ $option['key'] }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="source_id"><option value="">All Sources</option>@foreach ($accessSources as $source)<option value="{{ $source->id }}" @selected((string) request('source_id') === (string) $source->id)>{{ $source->name }} ({{ strtoupper($source->type) }})</option>@endforeach</select></div>
        <div class="col-md-1"><select class="form-select" name="enabled"><option value="">Any Status</option><option value="1" @selected(request('enabled') === '1')>Enabled</option><option value="0" @selected(request('enabled') === '0')>Disabled</option></select></div>
        <div class="col-md-1 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
    </form>
@endsection

@section('page-actions')
    <a href="{{ route('admin.access-bindings.create') }}" class="btn btn-primary">New Binding</a>
@endsection
