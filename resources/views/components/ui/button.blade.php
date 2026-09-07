@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'start',
    'iconSize' => 'sm',
])

@php
    $classes = 'ui-btn ui-btn-'.$variant;
    $iconPosition = in_array($iconPosition, ['start', 'end'], true) ? $iconPosition : 'start';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'start')
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'end')
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'start')
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'end')
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
    </button>
@endif
