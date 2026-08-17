@extends('layouts.admin-datatable')
@section('meta-page-title', 'Locks')
@section('page-title', 'Locks')
@section('page-pretitle', 'Hardware')

@section('page-actions')
    <a href="{{ route('admin.access-locks.create') }}" class="btn btn-primary">New Lock</a>
@endsection
