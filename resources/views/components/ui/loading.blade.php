@props(['label' => 'Memuat...'])
<span {{ $attributes->class(['ui-loading']) }} role="status" aria-live="polite">
    <span class="ui-spinner" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</span>
