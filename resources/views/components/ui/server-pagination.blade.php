@props([
    'paginator',
    'noun' => 'data',
])

<div
    {{ $attributes->class(['flex flex-col gap-3 border-t px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between']) }}
    style="border-color: var(--ui-line); background: var(--ui-bg-subtle)"
    data-pagination="server"
>
    <span style="color: var(--ui-fg-muted)">
        Menampilkan
        <span class="font-semibold" style="color: var(--ui-fg)">{{ $paginator->firstItem() ?: 0 }}–{{ $paginator->lastItem() ?: 0 }}</span>
        dari
        <span class="font-semibold" style="color: var(--ui-fg)">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
        {{ $noun }}
    </span>

    @if($paginator->hasPages())
        <div class="w-full sm:w-auto [&>nav]:text-sm">{{ $paginator->links() }}</div>
    @endif
</div>
