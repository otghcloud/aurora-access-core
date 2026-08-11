@extends('layouts.admin')
@section('meta-page-title', 'Edit System User')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit System User</h1>
        <a href="{{ route('admin.system-users.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if (session('new_api_token'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">New API token (shown once)</div>
            <code>{{ session('new_api_token') }}</code>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.system-users.update', $systemUser) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.management.users._form', ['systemUser' => $systemUser])
                        <button type="submit" class="btn btn-primary mt-4">Update User</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Generate API Token</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.system-users.tokens.store', $systemUser) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="token_name" class="form-label">Token Name</label>
                            <input type="text" class="form-control" id="token_name" name="token_name" value="{{ old('token_name') }}" required maxlength="255" placeholder="e.g. home-assistant">
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Generate Token</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Existing API Tokens</h2>
                </div>
                <div class="card-body">
                    @forelse ($tokens as $token)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $token->name }}</div>
                            <div class="small text-muted">Created: {{ $token->created_at?->format('d/m/Y H:i:s') }}</div>
                            <div class="small text-muted">Last Used: {{ $token->last_used_at?->format('d/m/Y H:i:s') ?? 'Never' }}</div>
                            <form method="POST" action="{{ route('admin.system-users.tokens.destroy', [$systemUser, $token]) }}" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke this token?')">Revoke</button>
                            </form>
                        </div>
                    @empty
                        <div class="text-muted">No tokens for this user.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
