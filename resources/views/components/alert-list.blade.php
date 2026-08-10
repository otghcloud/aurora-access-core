@props([
    'type' => 'info',
    'heading' => null,
    'items' => [],
])

@if (count($items) > 0)
	<x-alert-multiline :type="$type">
		@if ($heading)
			<x-slot:heading>{{ $heading }}</x-slot:heading>
		@endif
		<ul class="alert-list">
			@foreach ($items as $item)
				<li>{{ $item }}</li>
			@endforeach
		</ul>
	</x-alert-multiline>
@endif