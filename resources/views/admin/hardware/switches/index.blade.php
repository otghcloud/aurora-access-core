@extends('layouts.admin-datatable')
@section('meta-page-title', 'Access Switches')
@section('page-title', 'Switches')
@section('page-pretitle', 'Hardware')

@section('page-actions')
    <a href="{{ route('admin.access-switches.create') }}" class="btn btn-primary">New Switch</a>
@endsection
