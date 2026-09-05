@props([
    'name' => 'q',
    'value' => '',
    'placeholder' => 'Cari...',
    'id' => null,
    'width' => 'w-80',
    'buttonLabel' => 'Cari',
])

@php($inputId = $id ?: $name.'-search')

<div
    {{ $attributes->class(['flex items-stretch overflow-hidden rounded-lg border shadow-sm transition focus-within:ring-2']) }}
    style="border-color: var(--ui-line); background: var(--ui-bg); --tw-ring-color: var(--theme-content-accent)"
>
    <label for="{{ $inputId }}" class="sr-only">{{ $placeholder }}</label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        value="{{ $value }}"
        class="{{ $width }} min-w-0 border-0 bg-transparent px-3 py-2 text-sm outline-none focus:ring-0"
        style="color: var(--ui-fg)"
        placeholder="{{ $placeholder }}"
    >
    <button
        type="submit"
        class="inline-flex min-w-[7.5rem] items-center justify-center gap-2 border-l bg-[var(--theme-content-accent)] px-5 py-2 text-sm font-bold text-white transition hover:brightness-95 focus:outline-none dark:bg-orange-500 dark:hover:bg-orange-400"
        style="border-color: var(--ui-line)"
    >
        <x-ui-icon name="search" class="h-4 w-4" />
        <span>{{ $buttonLabel }}</span>
    </button>
</div>
