<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - {{ config('app.name', 'Access Control') }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <style>
            body {
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: radial-gradient(circle at top right, #e9f2ff, #f8fbff 45%, #ffffff 100%);
            }
        </style>
    </head>
    <body>
        <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 mb-3">Admin Login</h1>
                <p class="text-muted mb-4">Sign in to access the admin workspace.</p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3">
                    @csrf
                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control" required autofocus>
                    </div>
                    <div>
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Sign in</button>
                </form>
            </div>
        </div>
    </body>
</html>
