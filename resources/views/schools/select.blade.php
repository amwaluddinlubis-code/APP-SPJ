<x-layouts.public-tailwind title="Pilih Sekolah · SPJ BOSP">
    <div class="flex flex-col gap-8">
        <header class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[var(--theme-accent-soft)] text-[var(--theme-content-accent)]"><x-ui.icon name="school" size="lg" /></div>
                <div><p class="text-xs font-bold uppercase tracking-[.16em] text-[var(--theme-content-accent)]">Langkah 1 dari 2</p><h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[var(--ui-fg-strong)] sm:text-3xl">Pilih sekolah</h1><p class="mt-2 max-w-xl text-sm leading-6 text-[var(--ui-fg-muted)]">Tentukan ruang kerja sekolah yang akan digunakan. Tahun anggaran dan sumber dana dipilih setelah ini.</p></div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="secondary" icon="logout">Keluar</x-ui.button></form>
        </header>
        <nav aria-label="Progres pemilihan konteks" class="flex items-center gap-3 border-y border-[var(--ui-line)] py-4 text-xs font-bold"><span class="flex items-center gap-2 text-[var(--theme-content-accent)]"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--theme-accent)] text-white">1</span>Sekolah</span><span class="h-px flex-1 bg-[var(--ui-line)]"></span><span class="flex items-center gap-2 text-[var(--ui-fg-muted)]"><span class="flex h-6 w-6 items-center justify-center rounded-full border border-[var(--ui-line-strong)]">2</span>Tahun &amp; dana</span></nav>
        <livewire:school-selector />
    </div>
</x-layouts.public-tailwind>
