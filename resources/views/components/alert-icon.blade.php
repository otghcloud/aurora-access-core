@props(['type' => 'info'])

<div class="alert-icon">
	@if ($type === 'danger')
		<svg class="icon alert-icon icon-2" fill="none" height="24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
			<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
			<path d="M12 8v4"></path>
			<path d="M12 16h.01"></path>
		</svg>
	@elseif ($type === 'warning')
		<svg class="icon alert-icon icon-2" fill="none" height="24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
			<path d="M12 9v4"></path>
			<path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.871l-8.106 -13.534a1.914 1.914 0 0 0 -3.274 0z"></path>
			<path d="M12 16h.01"></path>
		</svg>
	@elseif ($type === 'success')
		<svg class="icon alert-icon icon-2" fill="none" height="24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
			<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
			<path d="M9 12l2 2l4 -4"></path>
		</svg>
	@else
		<svg class="icon alert-icon icon-2" fill="none" height="24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
			<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
			<path d="M12 9h.01"></path>
			<path d="M11 12h1v4h1"></path>
		</svg>
	@endif
</div>