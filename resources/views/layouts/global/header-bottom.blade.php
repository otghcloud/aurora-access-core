<header class="navbar-expand-md">
	@php
		$diagnosticsNavigationItems = app(\App\Services\AccessControl\DiagnosticsNavigationRegistry::class)->all();
	@endphp
	<div class="collapse navbar-collapse" id="navbar-menu">
		<div class="navbar">
			<div class="container-xl">
				<div class="row flex-column flex-md-row flex-fill align-items-center">
					<div class="col">
						<ul class="navbar-nav">
							<li class="nav-item{{ nav_active('admin') }}">
								<a class="nav-link" href="/admin">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-gauge-high fa-fw"></i>
									</span>
									<span class="nav-link-title"> Dashboard </span>
								</a>
							</li>

							<!-- Admin Dropdown -->
							<li class="nav-item dropdown{{ nav_active('admin/management/*') }}">
								<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-user-shield fa-fw"></i>
									</span>
									<span class="nav-link-title"> Admin </span>
								</a>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="{{ route('admin.system-users.index') }}"><i class="fa-solid fa-users fa-fw"></i>Users</a>
								</div>
							</li>

							<!-- System Dropdown -->
							<li class="nav-item dropdown{{ nav_active('admin/system/*') }}">
								<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-gears fa-fw"></i>
									</span>
									<span class="nav-link-title"> System </span>
								</a>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="{{ route('admin.system.configuration') }}"><i class="fa-solid fa-sliders fa-fw"></i>Configuration</a>
									<a class="dropdown-item" href="{{ route('admin.system.environment') }}"><i class="fa-solid fa-server fa-fw"></i>Environment</a>
								</div>
							</li>

							<!-- Access Dropdown -->
							<li class="nav-item dropdown{{ nav_active('admin/access/*') }}">
								<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-id-card-clip fa-fw"></i>
									</span>
									<span class="nav-link-title"> Access </span>
								</a>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="{{ route('admin.access-areas.index') }}"><i class="fa-solid fa-expand fa-fw"></i>Areas</a>
									<a class="dropdown-item" href="{{ route('admin.access-cards.index') }}"><i class="fa-solid fa-credit-card fa-fw"></i>Cards</a>
									<a class="dropdown-item" href="{{ route('admin.access-events.index') }}"><i class="fa-solid fa-calendar-check fa-fw"></i>Events</a>
									<a class="dropdown-item" href="{{ route('admin.access-area-permissions.index') }}"><i class="fa-solid fa-key fa-fw"></i>Permissions</a>
									<a class="dropdown-item" href="{{ route('admin.access-users.index') }}"><i class="fa-solid fa-users fa-fw"></i>Users</a>
								</div>
							</li>

							<!-- Hardware Dropdown -->
							<li class="nav-item dropdown{{ nav_active('admin/hardware/*') }}">
								<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-microchip fa-fw"></i>
									</span>
									<span class="nav-link-title"> Hardware </span>
								</a>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="{{ route('admin.access-bindings.index') }}"><i class="fa-solid fa-sliders fa-fw"></i>Bindings</a>
									<a class="dropdown-item" href="{{ route('admin.access-locks.index') }}"><i class="fa-solid fa-lock fa-fw"></i>Locks</a>
									<a class="dropdown-item" href="{{ route('admin.access-readers.index') }}"><i class="fa-solid fa-tablet fa-fw"></i>Readers</a>
									<a class="dropdown-item" href="{{ route('admin.access-switches.index') }}"><i class="fa-solid fa-toggle-on fa-fw"></i>Switches</a>
									<a class="dropdown-item" href="{{ route('admin.access-sensors.index') }}"><i class="fa-solid fa-wave-square fa-fw"></i>Sensors</a>
									<a class="dropdown-item" href="{{ route('admin.access-sources.index') }}"><i class="fa-solid fa-database fa-fw"></i>Sources</a>
								</div>
							</li>

							<!-- Diagnostic Dropdown -->
							<li class="nav-item dropdown{{ nav_active('admin/health/*') }}">
								<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
									<span class="nav-link-icon d-md-none d-lg-inline-block">
										<i class="fa-solid fa-stethoscope fa-fw"></i>
									</span>
									<span class="nav-link-title"> Diagnostics </span>
								</a>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="{{ route('admin.health') }}">Health</a>
									@foreach ($diagnosticsNavigationItems as $diagnosticsNavigationItem)
										<a class="dropdown-item" href="{{ route($diagnosticsNavigationItem['route']) }}">{{ $diagnosticsNavigationItem['label'] }}</a>
									@endforeach
								</div>
							</li>
						</ul>
					</div>

					<div class="col col-md-auto">
						<ul class="navbar-nav">
							<li class="nav-item{{ nav_active('/') }}">
								<form action="{{ route('logout') }}" method="POST">
									@csrf
									<button class="btn btn-outline-danger btn-sm" type="submit">Logout</button>
								</form>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</header>
