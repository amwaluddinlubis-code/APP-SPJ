@props(['paginator', 'noun' => 'data', 'compact' => false])

<div {{ $attributes->class(['flex flex-col gap-3 border-t px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between']) }}
    style="border-color: var(--ui-line); background: var(--ui-surface-soft)" data-pagination="server">
    <span class="{{ $compact ? 'hidden' : '' }}" style="color: var(--ui-fg-muted)">
        Menampilkan
        <span class="font-semibold"
            style="color: var(--ui-fg-strong)">{{ $paginator->firstItem() ?: 0 }}–{{ $paginator->lastItem() ?: 0 }}</span>
        dari
        <span class="font-semibold"
            style="color: var(--ui-fg-strong)">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
        {{ $noun }}
    </span>


    <div class="w-full sm:w-auto [&>nav]:text-sm">{{ $paginator->links() }}</div>
</div>
