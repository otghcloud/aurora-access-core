<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

	<head>
		<meta charset="utf-8">
		<meta content="width=device-width, initial-scale=1" name="viewport">
		<title>{{ $title ?? 'Admin' }} - {{ config('app.name', 'Access Control') }}</title>
		<link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" rel="stylesheet">
		<style>
			body {
				background: linear-gradient(180deg, #f2f6fc 0%, #ffffff 280px);
			}

			.admin-shell {
				min-height: 100vh;
			}

			.admin-brand {
				letter-spacing: 0.08rem;
				font-weight: 700;
			}

			@media (max-width: 991.98px) {
				.admin-nav-group {
					gap: 0.25rem;
				}

				.admin-nav-group .nav-item {
					width: 100%;
				}

				.admin-nav-group .dropdown-menu {
					position: static;
					float: none;
					width: 100%;
					margin-top: 0.25rem;
					margin-bottom: 0.5rem;
					box-shadow: none;
				}

				.admin-nav-group .dropdown-toggle::after {
					float: right;
					margin-top: 0.5rem;
				}
			}
		</style>
		@livewireStyles
	</head>

	<body>
		<div class="admin-shell d-flex flex-column">
			<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
				<div class="container-fluid px-4">
					<a class="navbar-brand admin-brand" href="{{ route('admin.dashboard') }}">Access Admin</a>
					<button aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler" data-bs-target="#adminNav" data-bs-toggle="collapse" type="button">
						<span class="navbar-toggler-icon"></span>
					</button>
					<div class="collapse navbar-collapse" id="adminNav">
						@php
							$adminUsersActive = request()->routeIs('admin.system-users.*');
							$accessActive = request()->routeIs('admin.access-users.*', 'admin.access-area-permissions.*', 'admin.access-areas.*', 'admin.access-cards.*', 'admin.access-events.*');
							$hardwareActive = request()->routeIs('admin.access-readers.*', 'admin.access-locks.*', 'admin.access-switches.*', 'admin.access-bindings.*', 'admin.access-sources.*');
							$diagnosticsActive = request()->routeIs('admin.health', 'admin.opc-diagnostics', 'admin.modbus-diagnostics*');
						@endphp
						<ul class="navbar-nav admin-nav-group me-auto mb-2 mb-lg-0 gap-lg-1">
							<li class="nav-item">
								<a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
							</li>
							<li class="nav-item dropdown">
								<a aria-expanded="false" class="nav-link dropdown-toggle {{ $adminUsersActive ? 'active' : '' }}" data-bs-toggle="dropdown" href="#" role="button">Admin</a>
								<ul class="dropdown-menu shadow-sm">
									<li><a class="dropdown-item {{ $adminUsersActive ? 'active' : '' }}" href="{{ route('admin.system-users.index') }}">Users</a></li>
								</ul>
							</li>
							<li class="nav-item dropdown">
								<a aria-expanded="false" class="nav-link dropdown-toggle {{ $accessActive ? 'active' : '' }}" data-bs-toggle="dropdown" href="#" role="button">Access</a>
								<ul class="dropdown-menu shadow-sm">
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-areas.*') ? 'active' : '' }}" href="{{ route('admin.access-areas.index') }}">Areas</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-cards.*') ? 'active' : '' }}" href="{{ route('admin.access-cards.index') }}">Cards</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-events.*') ? 'active' : '' }}" href="{{ route('admin.access-events.index') }}">Events</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-area-permissions.*') ? 'active' : '' }}" href="{{ route('admin.access-area-permissions.index') }}">Permissions</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-users.*') ? 'active' : '' }}" href="{{ route('admin.access-users.index') }}">Users</a></li>
								</ul>
							</li>
							<li class="nav-item dropdown">
								<a aria-expanded="false" class="nav-link dropdown-toggle {{ $hardwareActive ? 'active' : '' }}" data-bs-toggle="dropdown" href="#" role="button">Hardware</a>
								<ul class="dropdown-menu shadow-sm">
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-bindings.*') ? 'active' : '' }}" href="{{ route('admin.access-bindings.index') }}">Bindings</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-locks.*') ? 'active' : '' }}" href="{{ route('admin.access-locks.index') }}">Locks</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-readers.*') ? 'active' : '' }}" href="{{ route('admin.access-readers.index') }}">Readers</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.access-switches.*') ? 'active' : '' }}" href="{{ route('admin.access-switches.index') }}">Switches</a></li>										<li><a class="dropdown-item {{ request()->routeIs('admin.access-sensors.*') ? 'active' : '' }}" href="{{ route('admin.access-sensors.index') }}">Sensors</a></li>									<li><a class="dropdown-item {{ request()->routeIs('admin.access-sources.*') ? 'active' : '' }}" href="{{ route('admin.access-sources.index') }}">Sources</a></li>
								</ul>
							</li>
							<li class="nav-item dropdown">
								<a aria-expanded="false" class="nav-link dropdown-toggle {{ $diagnosticsActive ? 'active' : '' }}" data-bs-toggle="dropdown" href="#" role="button">Diagnostics</a>
								<ul class="dropdown-menu shadow-sm">
									<li><a class="dropdown-item {{ request()->routeIs('admin.health') ? 'active' : '' }}" href="{{ route('admin.health') }}">Health</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.opc-diagnostics') ? 'active' : '' }}" href="{{ route('admin.opc-diagnostics') }}">OPC</a></li>
									<li><a class="dropdown-item {{ request()->routeIs('admin.modbus-diagnostics*') ? 'active' : '' }}" href="{{ route('admin.modbus-diagnostics') }}">Modbus</a></li>
								</ul>
							</li>
						</ul>
						<div class="d-flex align-items-center gap-3">
							<span class="text-muted small">{{ auth()->user()->email }}</span>
							<form action="{{ route('logout') }}" method="POST">
								@csrf
								<button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
							</form>
						</div>
					</div>
				</div>
			</nav>

			<main class="container-fluid px-4 py-4">
				@if (session('status'))
					<div class="alert alert-success alert-dismissible fade show" role="alert">
						{{ session('status') }}
						<button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"></button>
					</div>
				@endif

				@if ($errors->any())
					<div class="alert alert-danger">
						<h2 class="h6">Please fix the following errors:</h2>
						<ul class="mb-0">
							@foreach ($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
				@endif

				@yield('content')
			</main>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
		@livewireScripts
	</body>

</html>
