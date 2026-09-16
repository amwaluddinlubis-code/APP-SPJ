<div class="space-y-5">
    <form wire:submit="selectContext" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2 sm:gap-5">
            <x-ui.field label="Tahun Anggaran" for="context-year" :error="$errors->first('selectedYear')" required>
                <div wire:key="year-dropdown" x-data="{
                    open: false,
                    selected: @entangle('selectedYear').live,
                    options: @js($years->values()),
                    select(value) { this.selected = value; this.open = false; },
                }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
                    <button type="button" id="context-year" class="context-dropdown-trigger" @click="open = !open"
                        :aria-expanded="open.toString()" aria-haspopup="listbox">
                        <span x-text="selected || 'Tahun Anggaran'"></span>
                        <x-ui.icon name="chevron-down" size="sm" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top role="listbox" aria-label="Tahun Anggaran"
                        class="context-dropdown-menu">
                        <button type="button" role="option" :aria-selected="selected === ''" class="context-dropdown-option"
                            @click="select('')">Tahun Anggaran</button>
                        <template x-for="option in options" :key="option">
                            <button type="button" role="option" :aria-selected="selected == option"
                                class="context-dropdown-option" @click="select(option)" x-text="option"></button>
                        </template>
                    </div>
                </div>
            </x-ui.field>
            <x-ui.field label="Sumber Dana" for="context-fund-source" :error="$errors->first('selectedFundSourceId')" required>
                <div wire:key="fund-source-dropdown-{{ $selectedYear ?? 'none' }}" x-data="{
                    open: false,
                    enabled: @js((bool) $selectedYear && $fundSources->isNotEmpty()),
                    selected: @entangle('selectedFundSourceId').live,
                    options: @js($fundSources->map(fn ($source) => ['id' => (string) $source->id, 'label' => $source->name])->values()),
                    select(value) { this.selected = value; this.open = false; },
                }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
                    <button type="button" id="context-fund-source" class="context-dropdown-trigger"
                        @click="if (enabled) open = !open" :aria-expanded="open.toString()" :disabled="!enabled"
                        aria-haspopup="listbox">
                        <span x-text="options.find(option => option.id == selected)?.label || 'Sumber Dana'"></span>
                        <x-ui.icon name="{{ !$selectedYear || $fundSources->isEmpty() ? 'lock' : 'chevron-down' }}" size="sm" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top role="listbox" aria-label="Sumber Dana"
                        class="context-dropdown-menu">
                        <button type="button" role="option" :aria-selected="selected === ''" class="context-dropdown-option"
                            @click="select('')">Sumber Dana</button>
                        <template x-for="option in options" :key="option.id">
                            <button type="button" role="option" :aria-selected="selected == option.id"
                                class="context-dropdown-option" @click="select(option.id)" x-text="option.label"></button>
                        </template>
                    </div>
                </div>
            </x-ui.field>
        </div>
        @if ($errors->has('selectedYear') || $errors->has('selectedFundSourceId'))
            <p
                class="mt-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm leading-6 text-rose-700">
                <x-ui.icon name="warning" size="sm" class="mt-1 shrink-0" /><span>Lengkapi tahun anggaran dan
                    sumber dana sebelum melanjutkan.</span></p>
        @endif
        <div class="mt-8 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
            <p wire:loading wire:target="selectContext"
                class="flex items-center gap-2 text-xs font-semibold text-[var(--theme-content-accent)]"><span
                    class="h-3 w-3 animate-spin rounded-full border-2 border-[var(--theme-accent-soft)] border-t-[var(--theme-accent)]"></span>Menyiapkan
                ruang kerja...</p>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="selectContext" icon="arrow-right"
                iconPosition="end" class="w-full justify-center sm:w-auto">Mulai Bekerja</x-ui.button>
        </div>
    </form>

    @if ($years->isEmpty())
        <div
            class="rounded-2xl border border-dashed border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-5 py-8 text-center">
            <div
                class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]">
                <x-ui.icon name="calendar" /></div>
            <p class="mt-3 font-bold text-[var(--ui-fg-strong)]">Belum ada pilihan yang tersedia</p>
            <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-[var(--ui-fg-muted)]">Simpan pengaturan ARKAS, lalu
                lakukan sinkronisasi untuk mengimpor tahun dan sumber dana.</p>
            <form method="POST" action="{{ route('years.synchronize') }}"
                data-confirm="Sinkronisasi akan mengimpor data tahun dan sumber dana dari ARKAS. Lanjutkan?"
                class="mt-5">@csrf<input type="hidden" name="confirm_sync" value="1"><x-ui.button type="submit"
                    icon="sync" class="w-full justify-center sm:w-auto">Sinkronkan ARKAS sekarang</x-ui.button>
            </form>
        </div>
    @elseif($selectedYear && $fundSources->isEmpty())
        <div
            class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
            <x-ui.icon name="warning" size="sm" class="mt-1 shrink-0" />
            <p>Belum ada sumber dana yang terhubung dengan tahun {{ $selectedYear }}. Pilih tahun lain atau lakukan
                sinkronisasi ARKAS.</p>
        </div>
    @endif
    <p class="pt-1 text-center text-xs leading-5 text-slate-500">Konteks ini menyaring seluruh data aplikasi. Anda dapat
        menggantinya kapan saja.</p>
</div>
