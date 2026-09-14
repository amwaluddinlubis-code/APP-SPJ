@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<section aria-labelledby="periodic-report-heading" class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-5 py-5 sm:px-6">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--ui-fg-muted)]">Pusat Laporan Pertanggungjawaban</p>
            <h2 id="periodic-report-heading" class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Paket laporan periodik</h2>
            <p class="mt-1 max-w-3xl text-sm text-[var(--ui-fg-muted)]">
                Pilih jenis periode untuk menyiapkan sumber data laporan. Kontrak modul laporan sudah dipisahkan dari template sehingga format dokumen dapat diperbarui tanpa mengubah data dan periode.
            </p>
        </div>

        @if($periodRequired)
            <div class="w-full lg:w-64">
                <label for="periodic-report-period" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Periode laporan</label>
                <x-ui.select id="periodic-report-period" wire:model.live="periode" class="w-full">
                    <option value="">Pilih periode</option>
                    @foreach($this->periodOptions() as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        @else
            <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2 text-sm">
                <span class="font-semibold text-[var(--ui-fg-strong)]">Periode:</span>
                <span class="text-[var(--ui-fg-muted)]">Tahun anggaran aktif</span>
            </div>
        @endif
    </div>

    <div class="mb-4 flex max-w-full overflow-x-auto rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-1" role="group" aria-label="Jenis paket laporan">
        @foreach($scopeLabels as $scopeKey => $scopeLabel)
            <button
                type="button"
                wire:click="setScope('{{ $scopeKey }}')"
                class="min-h-9 flex-1 whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold transition {{ $scope === $scopeKey ? 'text-white shadow-sm' : 'text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-base)] hover:text-[var(--ui-fg-strong)]' }}"
                @if($scope === $scopeKey) style="background: var(--theme-action-bg);" @endif
            >
                {{ $scopeLabel }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <div class="overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $scopeLabels[$scope] ?? 'Paket laporan' }}</h3>
                    <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ count($reportDefinitions) }} dokumen dalam paket ini</p>
                </div>
                <span class="rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1 text-xs font-bold text-[var(--ui-fg-muted)]">
                    {{ $summary['ready'] ? 'Data siap' : 'Menunggu periode' }}
                </span>
            </div>

            <div class="divide-y divide-[var(--ui-line)]">
                @foreach($reportDefinitions as $index => $report)
                    <div class="flex min-h-12 items-center gap-3 px-4 py-2.5" wire:key="periodic-report-{{ $scope }}-{{ $report['key'] }}">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-xs font-bold text-[var(--ui-fg-muted)]">
                            {{ $index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-[var(--ui-fg-strong)]">{{ $report['label'] }}</p>
                            <p class="text-xs text-[var(--ui-fg-muted)]">Kode modul: {{ strtoupper($report['key']) }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold {{ $summary['ready'] ? 'text-emerald-700' : 'text-[var(--ui-fg-muted)]' }}">
                            {{ $summary['ready'] ? 'Sumber data tersedia' : 'Pilih periode' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <aside aria-label="Ringkasan sumber data laporan" class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Ringkasan sumber data</p>
                    <p class="mt-1 text-sm font-bold text-[var(--ui-fg-strong)]">{{ $summary['period_label'] }}</p>
                    @if($summary['date_from'] && $summary['date_to'])
                        <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $summary['date_from'] }} s.d. {{ $summary['date_to'] }}</p>
                    @endif
                </div>
                <span class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-2 py-1 text-xs font-bold text-[var(--ui-fg-muted)]">
                    {{ $summary['transaction_count'] }} transaksi
                </span>
            </div>

            @if($summary['ready'])
                <dl class="mt-4 grid gap-2 sm:grid-cols-3 xl:grid-cols-1">
                    <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2.5">
                        <dt class="text-xs font-semibold text-[var(--ui-fg-muted)]">Nilai bruto</dt>
                        <dd class="mt-0.5 text-base font-bold text-[var(--ui-fg-strong)]">{{ $rupiah($summary['gross']) }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2.5">
                        <dt class="text-xs font-semibold text-[var(--ui-fg-muted)]">Total pajak</dt>
                        <dd class="mt-0.5 text-base font-bold text-amber-700">{{ $rupiah($summary['tax']) }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2.5">
                        <dt class="text-xs font-semibold text-[var(--ui-fg-muted)]">Nilai dibayarkan</dt>
                        <dd class="mt-0.5 text-base font-bold text-emerald-700">{{ $rupiah($summary['net']) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-[var(--ui-line)] pt-3">
                    <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">
                        Ringkasan ini berasal dari transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif. Formula serta tata letak resmi tiap dokumen akan mengikuti template laporan yang dipasang kemudian.
                    </p>
                </div>
            @else
                <div class="mt-4 rounded-lg border border-dashed border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-5 text-center">
                    <p class="text-sm font-semibold text-[var(--ui-fg-strong)]">Pilih periode untuk memuat sumber data.</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Daftar dokumen tetap tersedia, tetapi nilai laporan belum dihitung sampai periodenya ditentukan.</p>
                </div>
            @endif
        </aside>
    </div>
</section>
