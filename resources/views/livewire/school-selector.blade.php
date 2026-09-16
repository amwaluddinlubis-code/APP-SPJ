<div class="space-y-5">
    <label class="relative block">
        <span class="sr-only">Cari sekolah</span>
        <x-ui.icon name="search" size="sm"
            class="pointer-events-none absolute left-3.5 top-1/2 z-10 -translate-y-1/2 text-slate-400" />
        <x-ui.input id="school-search" wire:model.live.debounce.300ms="search" placeholder="Cari sekolah atau NPSN"
            autocomplete="off" class="context-search !border-0 !pl-10 !shadow-none" />
    </label>
    <div wire:loading wire:target="search"
        class="flex items-center gap-2 text-xs font-semibold text-[var(--theme-content-accent)]"><span
            class="h-3 w-3 animate-spin rounded-full border-2 border-[var(--theme-accent-soft)] border-t-[var(--theme-accent)]"></span>Memperbarui
        daftar...</div>
    <div class="space-y-1">
        @forelse($schools as $school)
            @php(
    $initials = mb_strtoupper(
        collect(preg_split('/\s+/', trim($school->name)))->filter()->map(fn($word) => mb_substr($word, 0, 1))->take(2)->implode('') ?:
        'S'
    )
)
            <article wire:key="school-{{ $school->id }}"
                class="school-card group flex flex-col gap-4 rounded-xl p-3 transition sm:flex-row sm:items-center sm:justify-between sm:p-4">
                <div class="flex min-w-0 items-center gap-3.5">
                    <div class="monogram flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold tracking-wide"
                        aria-hidden="true">{{ $initials }}</div>
                    <div class="min-w-0">
                        <h3 class="truncate text-[15px] font-medium text-slate-900">{{ $school->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">NPSN {{ $school->npsn }}</p>
                    </div>
                </div>
                <x-ui.button type="button" wire:click="selectSchool({{ $school->id }})" wire:loading.attr="disabled"
                    wire:target="selectSchool({{ $school->id }})" icon="arrow-right" iconPosition="end"
                    class="w-full justify-center sm:w-auto sm:shrink-0">Pilih</x-ui.button>
            </article>
        @empty
            <div
                class="rounded-2xl border border-dashed border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-5 py-10 text-center">
                <div
                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]">
                    <x-ui.icon name="search" /></div>
                <p class="mt-3 font-bold text-[var(--ui-fg-strong)]">Sekolah tidak ditemukan</p>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Coba gunakan nama atau NPSN yang berbeda.</p>
            </div>
        @endforelse
    </div>
</div>
