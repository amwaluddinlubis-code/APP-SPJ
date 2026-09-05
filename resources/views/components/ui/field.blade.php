@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->class(['space-y-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif
            class="block text-sm font-semibold text-[var(--ui-fg-strong)]">
            {{ $label }}
            @if ($required)
                <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-xs font-medium text-rose-600">{{ $error }}</p>
    @elseif($hint)
        <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">{{ $hint }}</p>
    @endif
</div>
