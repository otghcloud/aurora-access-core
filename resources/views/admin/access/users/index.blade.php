@extends('layouts.admin-datatable')
@section('meta-page-title', 'Access Users')
@section('page-title', 'Users')
@section('page-pretitle', 'Access')

@section('page-actions')
    <a href="{{ route('admin.access-users.create') }}" class="btn btn-primary">New User</a>
@endsection
