@extends('layouts.admin-datatable')
@section('meta-page-title', 'Sensors')
@section('page-title', 'Sensors')
@section('page-pretitle', 'Hardware')

@section('page-actions')
    <a href="{{ route('admin.access-sensors.create') }}" class="btn btn-primary">New Sensor</a>
@endsection
