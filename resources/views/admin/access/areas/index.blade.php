@extends('layouts.admin-datatable')
@section('meta-page-title', 'Areas')
@section('page-title', 'Areas')
@section('page-pretitle', 'Access')

@section('page-actions')
    <a href="{{ route('admin.access-areas.create') }}" class="btn btn-primary">New Area</a>
@endsection
