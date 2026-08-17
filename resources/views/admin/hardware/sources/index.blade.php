@extends('layouts.admin-datatable')
@section('meta-page-title', 'Access Sources')
@section('page-title', 'Sources')
@section('page-pretitle', 'Hardware')

@section('page-actions')
    <a href="{{ route('admin.access-sources.create') }}" class="btn btn-primary">New Source</a>
@endsection
