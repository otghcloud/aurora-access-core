@extends('layouts.admin-datatable')
@section('meta-page-title', 'Access Readers')
@section('page-title', 'Readers')
@section('page-pretitle', 'Hardware')

@section('page-actions')
    <a href="{{ route('admin.access-readers.create') }}" class="btn btn-primary">New Reader</a>
@endsection
