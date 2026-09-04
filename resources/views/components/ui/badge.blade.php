@props(['variant' => 'theme'])
@php
    $variant = in_array($variant, ['theme','neutral','success','warning','danger'], true) ? $variant : 'theme';
    $class = $variant === 'neutral' ? 'ui-badge' : 'ui-badge ui-badge-'.$variant;
@endphp
<span {{ $attributes->class([$class]) }}>{{ $slot }}</span>
