{{--
    Multiline alert — heading + body text, optional right-aligned button.
    The $heading slot accepts rich HTML (e.g. <strong> tags).

    Usage (no button):
        <x-alert-multiline type="warning">
            <x-slot:heading>Heading text</x-slot:heading>
            Body description here.
        </x-alert-multiline>

    Usage (with button):
        <x-alert-multiline type="danger">
            <x-slot:heading>Heading text</x-slot:heading>
            Body description here.
            <x-slot:actions><a class="btn btn-sm btn-danger" href="#">Action</a></x-slot:actions>
        </x-alert-multiline>

    Usage (no heading):
        <x-alert-multiline type="info">Body description only.</x-alert-multiline>
--}}
@props(['type' => 'info'])

<div class="alert alert-{{ $type }}" role="alert">
	<x-alert-icon :type="$type" />
	@if (isset($actions) && $actions->isNotEmpty())
		<div class="row w-100 g-2 align-items-center">
			<div class="col">
				@if (isset($heading) && $heading->isNotEmpty())
					<h4 class="alert-heading">{!! $heading !!}</h4>
				@endif
				<div class="alert-description">{{ $slot }}</div>
			</div>
			<div class="col-auto">{{ $actions }}</div>
		</div>
	@else
		<div>
			@if (isset($heading) && $heading->isNotEmpty())
				<h4 class="alert-heading">{!! $heading !!}</h4>
			@endif
			<div class="alert-description">{{ $slot }}</div>
		</div>
	@endif
</div>
