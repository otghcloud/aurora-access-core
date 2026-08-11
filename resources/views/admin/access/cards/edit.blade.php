@extends('layouts.admin')
@section('meta-page-title', 'Edit Access Card')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Access Card</h1>
        <a href="{{ route('admin.access-cards.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.access-cards.update', $accessCard) }}">
                @csrf
                @method('PUT')
                @include('admin.access.cards._form', ['accessCard' => $accessCard, 'accessUsers' => $accessUsers])
                <button type="submit" class="btn btn-primary mt-4">Update Card</button>
            </form>
        </div>
    </div>
@endsection
