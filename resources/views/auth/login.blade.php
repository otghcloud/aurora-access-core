@extends('auth.base')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h1 class="h2 text-center mb-4">Please Login</h1>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('login.store') }}" method="POST" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input autofocus autocomplete="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="hello@example.com" required type="email" value="{{ old('email') }}">
                    @error('email')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input autocomplete="current-password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required type="password">
                    @error('password')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
                <div class="mb-2">
                    <label class="form-check">
                        <input {{ old('remember') ? 'checked' : '' }} class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <span class="form-check-label" for="remember">Remember me on this device</span>
                    </label>
                </div>
                <div class="form-footer">
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </div>
            </form>
        </div>
    </div>
@endsection
