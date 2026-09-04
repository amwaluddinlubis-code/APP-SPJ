@props(['size' => 'md', 'label' => 'Memuat...'])
@php
    $spinnerSize = match ($size) {
        'sm' => '.8rem',
        'lg' => '1.5rem',
        default => '1rem',
    };
@endphp
<div class="flex items-center justify-center">
    <span class="ui-loading" role="status" aria-live="polite">
        <span class="ui-spinner" style="width: {{ $spinnerSize }}; height: {{ $spinnerSize }}" aria-hidden="true"></span>
        <span class="sr-only">{{ $label }}</span>
    </span>
</div>
