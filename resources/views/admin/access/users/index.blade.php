@extends('layouts.admin')
@section('meta-page-title', 'Access Users')
@section('page-title', 'Users')
@section('page-pretitle', 'Access')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Users</h1>
        <a href="{{ route('admin.access-users.create') }}" class="btn btn-primary">New User</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            {{ $dataTable->table(['class' => 'table table-hover align-middle mb-0']) }}
        </div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
