<!doctype html>
<html lang="en">

	<head>
		<meta charset="utf-8" />
		<meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport" />
		<meta content="{{ csrf_token() }}" name="csrf-token" />
		<meta content="ie=edge" http-equiv="X-UA-Compatible" />
		<title>@yield('meta-page-title') :: {{ config('app.name', 'Aurora Access Controller') }}</title>
		<meta content="#662071" name="msapplication-TileColor" />
		<meta content="#662071" name="theme-color" />
		<meta content="black" name="apple-mobile-web-app-status-bar-style" />
		<meta content="yes" name="apple-mobile-web-app-capable" />
		<meta content="yes" name="mobile-web-app-capable" />
		<meta content="True" name="HandheldFriendly" />
		<meta content="320" name="MobileOptimized" />
		<!--<link href="/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">
		<link href="/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png">
		<link href="/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png">
		<link href="/site.webmanifest" rel="manifest">-->
		<meta content="" name="description" />

		@vite(['packages/access-core/resources/sass/admin.scss', 'packages/access-core/resources/js/admin.js'], 'vendor/aurora-access-core/build')
		@livewireStyles

	</head>

	<body>
		<div class="page">
			@include('layouts.global.header')
			<div class="page-wrapper">

				<div aria-label="Page header" class="page-header d-print-none">
					<div class="container-xl">
						<div class="row g-2 align-items-center">
							<div class="col">
								@hasSection('page-pretitle')
									<div class="page-pretitle">@yield('page-pretitle')</div>
								@endif
								@hasSection('page-title')
									<h2 class="page-title">@yield('page-title')</h2>
								@endif
							</div>

							@hasSection('page-actions')
								<div class="col-auto ms-auto">
									<div class="btn-list d-flex flex-wrap justify-content-end gap-2">
										@yield('page-actions')
									</div>
								</div>
							@endif

						</div>
					</div>
				</div>

				<!-- Breadcrumbs -->

				<div class="page-body">

					@if (session('success'))
						<div class="container">
							<x-alert type="success">{{ session('success') }}</x-alert>
						</div>
					@endif

					@if (session('error'))
						<div class="container">
							<x-alert type="danger">{{ session('error') }}</x-alert>
						</div>
					@endif

					@if ($errors->any())
						<div class="container">
							<x-alert-list :items="$errors->all()" heading="Validation Errors:" type="danger" />
						</div>
					@endif

					@hasSection('page-alerts')
						<div class="container">
							@yield('page-alerts')
						</div>
					@endif

					<div class="container">
					@yield('content')
					</div>

				</div>

				@include('layouts.global.footer')

			</div>
		</div>

		@stack('scripts')
		@livewireScripts
	</body>

</html>