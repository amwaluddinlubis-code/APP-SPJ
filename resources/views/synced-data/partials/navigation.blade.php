<nav class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 shadow" aria-label="Navigasi kelompok data">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('synced-data.index') }}" class="rounded-lg px-3 py-2 text-base font-semibold transition {{ $type === 'overview' ? 'bg-[var(--theme-content-accent)] text-white shadow' : 'text-[var(--ui-fg)] hover:bg-[var(--ui-surface-soft)]' }}">Semua Data</a>
        @foreach($groups as $group => $items)
            <span class="rounded-lg bg-[var(--ui-surface-soft)] px-3 py-2 text-base font-semibold text-[var(--ui-fg)]">
                {{ $group }} <span class="ml-1 text-[var(--ui-fg-muted)]">{{ $items->count() }}</span>
            </span>
        @endforeach
    </div>
</nav>
