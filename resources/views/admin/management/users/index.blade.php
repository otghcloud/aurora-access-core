@extends('layouts.admin-datatable')
@section('meta-page-title', 'System Users')
@section('page-title', 'System Users')
@section('page-pretitle', 'Administration')

@section('page-actions')
    <a href="{{ route('admin.system-users.create') }}" class="btn btn-primary">New System User</a>
@endsection
