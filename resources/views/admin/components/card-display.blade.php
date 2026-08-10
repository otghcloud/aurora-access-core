@php
    $displayCard = $accessCard ?? null;
    $displayDescription = $displayCard?->description ?: ($displayCard ? 'Card #'.$displayCard->id : null);
@endphp

@if ($displayCard)
    <a href="{{ route('admin.access-cards.show', $displayCard) }}">{{ $displayDescription }}</a>
@else
    -
@endif