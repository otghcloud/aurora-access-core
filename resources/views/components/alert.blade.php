{{--
    Simple (single-row) alert.
    Usage:
        <x-alert type="warning">Message text</x-alert>
        <x-alert type="danger">
            Message text
            <x-slot:actions><a class="btn btn-sm btn-danger" href="#">Action</a></x-slot:actions>
        </x-alert>
--}}
@props(['type' => 'info'])

<div class="alert alert-{{ $type }}" role="alert">
	<x-alert-icon :type="$type" />
	@if (isset($actions) && $actions->isNotEmpty())
		<div class="row w-100 g-2 align-items-center">
			<div class="col">{{ $slot }}</div>
			<div class="col-auto">{{ $actions }}</div>
		</div>
	@else
		<div>{{ $slot }}</div>
	@endif
</div>
