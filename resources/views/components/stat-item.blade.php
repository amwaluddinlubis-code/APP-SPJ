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
        <div class="ui-stat-heading flex items-center gap-2.5">
            <span
                class="ui-stat-icon inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border {{ $iconClass }}"
                style="border-color: color-mix(in srgb, currentColor 24%, transparent); background: color-mix(in srgb, currentColor 10%, transparent)"
            >
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
