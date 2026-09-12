<section class="ui-filter-panel">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--ui-line)] px-5 py-3">
        <p class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide" style="color: var(--theme-content-accent)">
            <x-ui.icon name="filter" size="sm" />
            <span>Filter Penganggaran</span>
        </p>
        <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="clearFilters">
            Reset
        </x-ui.button>
    </div>

    <div class="space-y-3 p-5">
        <div class="flex flex-wrap items-end gap-2">
            <div class="space-y-1.5">
                <span class="block text-sm font-semibold text-[var(--ui-fg-strong)]" id="rkas-filter-mode-label">Periode</span>
                <div class="flex overflow-hidden rounded-lg border border-[var(--ui-line)] text-sm" role="group" aria-labelledby="rkas-filter-mode-label">
                    @foreach ($modes as $m => $label)
                        <button
                            type="button"
                            wire:click="$set('mode', '{{ $m }}')"
                            aria-pressed="{{ $mode === $m ? 'true' : 'false' }}"
                            class="px-3 py-2 transition"
                            @if ($mode === $m) style="background: var(--theme-action-bg); color: var(--theme-action-fg); font-weight: 700;" @else style="color: var(--ui-fg-muted); background: var(--ui-surface-base);" @endif
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if ($mode === 'bulan')
                <x-ui.field label="Bulan" for="rkas-filter-periode" class="min-w-[12rem]">
                    <x-ui.select id="rkas-filter-periode" wire:model.live="periode">
                        <option value="">Pilih bulan…</option>
                        @foreach ($bulanOptions as $b)
                            <option value="{{ $b['id'] }}">{{ $b['nama'] }} ({{ $b['n'] }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @elseif ($mode === 'triwulan')
                <x-ui.field label="Triwulan" for="rkas-filter-periode" class="min-w-[12rem]">
                    <x-ui.select id="rkas-filter-periode" wire:model.live="periode">
                        <option value="">Pilih triwulan…</option>
                        @foreach ($twOptions as $o)
                            <option value="{{ $o['id'] }}">{{ $o['nama'] }} ({{ $o['n'] }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @elseif ($mode === 'semester')
                <x-ui.field label="Semester" for="rkas-filter-periode" class="min-w-[12rem]">
                    <x-ui.select id="rkas-filter-periode" wire:model.live="periode">
                        <option value="">Pilih semester…</option>
                        @foreach ($semOptions as $o)
                            <option value="{{ $o['id'] }}">{{ $o['nama'] }} ({{ $o['n'] }})</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
            @endif

            <x-ui.field label="Cari" for="rkas-filter-q" class="min-w-[11rem] flex-1">
                <x-ui.input id="rkas-filter-q" type="search" wire:model.live.debounce.300ms="q" placeholder="Cari uraian / rekening..." />
            </x-ui.field>
        </div>

        <div class="grid gap-2 md:grid-cols-3">
            <x-ui.field label="Program" for="rkas-filter-program">
                <x-ui.select id="rkas-filter-program" wire:model.live="program">
                    <option value="">Semua Program</option>
                    @foreach ($programOptions as $p)
                        <option value="{{ $p['kode'] }}">{{ $p['kode'] }} - {{ $p['nama'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Sub Program" for="rkas-filter-sub">
                <x-ui.select id="rkas-filter-sub" wire:model.live="sub" :disabled="! $program">
                    <option value="">Semua Sub Program</option>
                    @foreach ($subOptions as $s)
                        <option value="{{ $s['kode'] }}">{{ $s['kode'] }} - {{ $s['nama'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Kegiatan" for="rkas-filter-kegiatan">
                <x-ui.select id="rkas-filter-kegiatan" wire:model.live="kegiatan" :disabled="! $sub">
                    <option value="">Semua Kegiatan</option>
                    @foreach ($kegiatanOptions as $k)
                        <option value="{{ $k['kode'] }}">{{ $k['kode'] }} - {{ $k['nama'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </div>
    </div>
</section>
