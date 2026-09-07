@props([
    'label',
    'value',
    'hint' => null,
    'valueClass' => null,
    'icon' => null,
    'iconClass' => null,
])

<div {{ $attributes->class(['ui-stat']) }}>
    @if($icon)
        <div class="ui-stat-heading">
            <span class="ui-stat-icon {{ $iconClass }}">
                <x-ui-icon :name="$icon" class="h-5 w-5" />
            </span>
            <p class="ui-stat-label">{{ $label }}</p>
        </div>
    @else
        <p class="ui-stat-label">{{ $label }}</p>
    @endif
    <p class="ui-stat-value {{ $valueClass }}">{{ $value }}</p>
    @if($hint)
        <p class="ui-stat-hint">{{ $hint }}</p>
    @endif
</div>
