@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Access Readers</h1>
        <a href="{{ route('admin.access-readers.create') }}" class="btn btn-primary">New Reader</a>
    </div>

    <livewire:admin.access-readers-table />
@endsection
