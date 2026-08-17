@extends('layouts.admin-datatable')
@section('meta-page-title', 'Access Cards')
@section('page-title', 'Access Credentials')
@section('page-pretitle', 'Access')

@section('page-actions')
    <a href="{{ route('admin.access-cards.create') }}" class="btn btn-primary">New Card</a>
@endsection
