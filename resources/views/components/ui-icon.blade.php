@props(['name'])

{{-- Compatibility wrapper for legacy <x-ui-icon> consumers.
     New or touched markup must use <x-ui.icon> directly. --}}
<x-ui.icon :name="$name" {{ $attributes }} />
