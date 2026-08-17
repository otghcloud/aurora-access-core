<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login :: {{ config('app.name', 'Aurora Access') }}</title>
    @vite(['packages/access-core/resources/sass/admin.scss', 'packages/access-core/resources/js/admin.js'], 'vendor/aurora-access-core/build')
</head>

<body>
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <a aria-label="{{ config('app.name', 'Aurora Access') }}" class="navbar-brand navbar-brand-autodark" href="/">
                    {{ config('app.name', 'Aurora Access') }}
                </a>
            </div>

            @yield('content')

            <div class="text-center text-secondary mt-3">
                <small>&copy; {{ date('Y') }} {{ config('app.name', 'Aurora Access') }}</small>
            </div>
        </div>
    </div>
</body>

</html>
