@props([
    'label',
    'value',
    'hint' => null,
    'valueClass' => null,
])

<div {{ $attributes->class(['ui-stat']) }}>
    <p class="ui-stat-label">{{ $label }}</p>
    <p class="ui-stat-value {{ $valueClass }}">{{ $value }}</p>
    @if($hint)
        <p class="ui-stat-hint">{{ $hint }}</p>
    @endif
</div>
