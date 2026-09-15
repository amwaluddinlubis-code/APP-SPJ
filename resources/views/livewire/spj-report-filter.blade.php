@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $periodQuery = match ($mode) {
        'bulan' => ['month' => $periode],
        'triwulan' => ['quarter' => $periode],
        'semester' => ['semester' => $periode],
        default => [],
    };
    $exportQuery = array_filter(['tab' => 'laporan', ...$periodQuery]);
@endphp
<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
        <div class="grid items-stretch gap-4 xl:grid-cols-5">
            <section aria-label="Filter laporan" class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3 sm:p-4 xl:col-span-3">
                <div class="spj-report-toolbar flex h-full flex-col justify-center gap-3">
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3">
                        <span id="spj-report-period-label" class="shrink-0 whitespace-nowrap text-sm font-semibold text-[var(--ui-fg-strong)]">Periode</span>
                        <div class="flex min-w-0 flex-wrap items-center gap-x-6 gap-y-2">
                            <div class="flex w-fit max-w-full overflow-x-auto rounded-lg border border-[var(--ui-line)] text-sm" role="group" aria-labelledby="spj-report-period-label">
                                @foreach ($this->modes() as [$modeOption, $label])
                                    <button type="button" wire:click="setMode('{{ $modeOption }}')" class="whitespace-nowrap px-3 py-2 transition {{ $mode === $modeOption ? 'font-bold text-white' : 'text-[var(--ui-fg-muted)]' }}" style="{{ $mode === $modeOption ? 'background: var(--theme-action-bg);' : 'background: var(--ui-surface-base);' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <label for="spj-report-row-count" class="text-sm font-semibold text-[var(--ui-fg-strong)]">Baris</label>
                                <x-ui.select id="spj-report-row-count" wire:model.live="perPage" aria-label="Baris per halaman"
                                    class="!min-h-9 !w-auto !py-1.5 !text-xs">
                                    <option value="10">10 baris</option>
                                    <option value="15">15 baris</option>
                                    <option value="25">25 baris</option>
                                    <option value="50">50 baris</option>
                                    <option value="100">100 baris</option>
                                </x-ui.select>
                            </div>
                        </div>
                        <label for="spj-report-periode" class="shrink-0 whitespace-nowrap text-sm font-semibold text-[var(--ui-fg-strong)]">Pilih periode</label>
                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                            <x-ui.select id="spj-report-periode" wire:model.live="periode" class="min-w-[12rem] flex-1" style="width: auto;">
                                <option value="">{{ $mode === 'semua' ? 'Semua periode' : 'Pilih '.$mode }}</option>
                                @if($mode === 'semester')
                                    @foreach(range(1,2) as $semester)<option value="{{ $semester }}">Semester {{ $semester }}</option>@endforeach
                                @elseif($mode === 'triwulan')
                                    @foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach
                                @elseif($mode === 'bulan')
                                    @foreach(range(1,12) as $month)<option value="{{ $month }}">{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach
                                @endif
                            </x-ui.select>
                            <x-ui.action-menu label="Ekspor">
                                <div class="ui-action-menu-label">Susun Laporan</div>
                                <a class="ui-action-menu-item" href="{{ route('spj.honor-payments.select', $exportQuery) }}">Honor Pegawai</a>
                                <a class="ui-action-menu-item" href="{{ route('spj.service-recipients.select', $exportQuery) }}">Jasa Lainnya</a>
                            </x-ui.action-menu>
                        </div>
                    </div>
                </div>
            </section>
            <section aria-label="Ringkasan laporan" class="grid grid-cols-2 content-center gap-3 xl:col-span-2">
                @foreach([['Paket sukses',$summary['count'] ?? 0,'text-indigo-700'],['Paket dibatalkan',$summary['cancelled_count'] ?? 0,'text-rose-700'],['Nilai bruto',$rupiah($summary['gross'] ?? 0),'text-slate-800'],['Nilai dibayarkan',$rupiah($summary['net'] ?? 0),'text-emerald-700']] as [$label,$value,$color])
                    <div class="flex min-h-[4.25rem] h-full flex-row items-center justify-between gap-2 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="text-right text-xl font-extrabold {{ $color }} [overflow-wrap:anywhere]">{{ $value }}</p>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
    <div class="overflow-x-auto p-5"><table data-pagination="server" class="min-w-full divide-y divide-[var(--ui-line)] text-base"><thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NOMOR SPJ</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">STATUS</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">BUKTI / TANGGAL</th><th class="px-4 py-3 text-left text-xs font-bold text-slate-500">PENERIMA</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">BRUTO</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">PAJAK</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">DIBAYARKAN</th><th class="px-4 py-3 text-right text-xs font-bold text-slate-500">TINDAKAN</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">
            @php
                $isCancelled = false;
            @endphp
            @forelse($packages ?? [] as $package)
                @php
                    $isCancelled = $package->report_status === 'CANCELLED';
                    $packageUrl = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]);
                @endphp
                <tr wire:key="spj-report-{{ $package->id }}" class="transition {{ $isCancelled ? 'bg-rose-50/70 text-slate-500' : 'hover:bg-indigo-50/40' }}">
                    <td class="px-4 py-3 font-mono text-xs font-bold {{ $isCancelled ? 'text-rose-700 line-through' : 'text-indigo-700' }}">
                        <a href="{{ $packageUrl }}" class="hover:underline">{{ $package->report_document_number }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isCancelled ? 'border border-rose-200 bg-rose-100 text-rose-800' : 'border border-emerald-200 bg-emerald-100 text-emerald-800' }}">{{ $isCancelled ? 'Dibatalkan' : 'Sukses' }}</span>
                        @if ($isCancelled && $package->report_cancellation_reason)
                            <p class="mt-1 max-w-48 text-xs text-rose-700">{{ $package->report_cancellation_reason }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold">{{ $package->transaction->no_bukti }}</p>
                        <p class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p>
                    </td>
                    <td class="px-4 py-3">{{ $package->transaction->recipient_name }}</td>
                    <td class="px-4 py-3 text-right">{{ $rupiah($package->transaction->gross_amount) }}</td>
                    <td class="px-4 py-3 text-right {{ $isCancelled ? 'text-slate-400' : 'text-amber-700' }}">{{ $rupiah($package->transaction->tax_total) }}</td>
                    <td class="px-4 py-3 text-right font-bold {{ $isCancelled ? 'text-slate-400' : 'text-emerald-700' }}">{{ $rupiah($package->transaction->net_amount) }}</td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.action-menu label="Tindakan">
                            <button type="button" class="ui-action-menu-item w-full text-left" data-template-preview="{{ route('spj.preview-package', $package->id) }}" data-template-preview-pdf="{{ route('spj.preview-package-pdf', $package->id) }}" data-template-name="Pratinjau {{ $package->report_document_number }}">Preview dokumen</button>
                            @if (! $isCancelled)
                                <form method="POST" action="{{ route('spj.download', $package->id) }}">@csrf<button type="submit" class="ui-action-menu-item w-full text-left">Download PDF</button></form>
                                <form method="POST" action="{{ route('spj.download-package-excel', $package->id) }}">@csrf<button type="submit" class="ui-action-menu-item w-full text-left">Download Excel</button></form>
                            @endif
                            <a class="ui-action-menu-item" href="{{ $packageUrl }}">Buka Paket</a>
                        </x-ui.action-menu>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-14 text-center text-slate-500">Belum ada riwayat paket SPJ untuk filter ini.</td></tr>
            @endforelse
        </tbody></table></div>
    @if($packages->hasPages())
        <x-ui.server-pagination :paginator="$packages" noun="paket" />
    @endif
</div>
