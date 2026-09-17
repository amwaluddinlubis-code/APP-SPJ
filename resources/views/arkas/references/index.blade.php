<x-layouts.tailwind-app>
    @php
        $tabs = [
            'programs' => 'Program',
            'subprograms' => 'Sub Program',
            'activities' => 'Kegiatan',
            'accounts' => 'Rekening',
            'acuanBarang' => 'Acuan Barang',
        ];
    @endphp

    <div class="space-y-6">
        <x-page-header title="Referensi ARKAS" subtitle="Referensi yang digunakan Penganggaran dan penyusunan laporan." kicker="Data Baca-saja" icon="database">
            <x-slot:actions>
                <span class="ui-btn ui-btn-secondary px-3 py-2 text-base">{{ $contextLabel }}</span>
            </x-slot:actions>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                @foreach ($tabs as $key => $label)
                    <div class="px-5 py-3.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $label }}</p>
                        <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ number_format($counts[$key], 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ui-line)] p-4 sm:p-5">
                <nav class="ui-tabs w-full" aria-label="Jenis referensi ARKAS">
                    <div class="ui-tabs-list" role="tablist">
                        @foreach ($tabs as $key => $label)
                            <a href="{{ route('arkas.references', ['type' => $key]) }}" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}" class="ui-tab {{ $type === $key ? 'ui-tab-active' : '' }}">
                                <x-ui.icon :name="match ($key) { 'programs' => 'budget', 'subprograms' => 'queue', 'activities' => 'work', default => 'number' }" size="sm" />
                                <span>{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
                <div class="flex w-full justify-end">
                    <form method="get" class="flex w-full flex-wrap items-center justify-end gap-4 sm:w-auto">
                        <input type="hidden" name="type" value="{{ $type }}">
                        <div class="flex w-full sm:w-auto">
                            <x-ui.input name="q" value="{{ $search }}" placeholder="Cari kode atau nama..." aria-label="Cari referensi" class="w-full rounded-r-none sm:w-80 lg:w-96" />
                            <x-ui.button type="submit" variant="secondary" class="rounded-l-none">Cari</x-ui.button>
                        </div>
                        <label class="flex items-center gap-2 whitespace-nowrap text-sm text-[var(--ui-fg-muted)]">
                            <span>Baris per halaman</span>
                            <x-ui.select name="perPage" aria-label="Baris per halaman" onchange="this.form.submit()">
                                @foreach ([25, 50, 100] as $option)
                                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                                @endforeach
                            </x-ui.select>
                        </label>
                    </form>
                </div>
            </div>

            @if ($type === 'acuanBarang')
                @include('arkas.references.partials.acuan-barang', ['rows' => $rows])
            @else
                @include('arkas.references.partials.table', ['rows' => $rows, 'type' => $type])
            @endif
        </section>
    </div>
</x-layouts.tailwind-app>
