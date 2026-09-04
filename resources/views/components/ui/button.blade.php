@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php($classes = 'ui-btn ui-btn-'.$variant)

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
