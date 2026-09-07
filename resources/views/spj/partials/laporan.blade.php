<div x-show="tab === 'laporan'" x-transition>
    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
        <form method="GET" class="spj-report-toolbar flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="laporan">
            <div><label class="text-xs font-bold text-slate-500">BULAN</label><select name="month" class="mt-1 block rounded-lg border-[var(--ui-line-strong)] text-base"><option value="">Semua bulan</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(request('month') == $month)>{{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}</option>@endforeach</select></div>
            <div><label class="text-xs font-bold text-slate-500">TRIWULAN</label><select name="quarter" class="mt-1 block rounded-lg border-[var(--ui-line-strong)] text-base"><option value="">Semua triwulan</option>@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}" @selected(request('quarter') == $quarter)>Triwulan {{ $quarter }}</option>@endforeach</select></div>
            <div><label class="text-xs font-bold text-slate-500">SEMESTER</label><select name="semester" class="mt-1 block rounded-lg border-[var(--ui-line-strong)] text-base"><option value="">Semua semester</option><option value="1" @selected(request('semester') == 1)>Semester 1</option><option value="2" @selected(request('semester') == 2)>Semester 2</option></select></div>
            <button class="rounded-lg bg-violet-600 px-4 py-2.5 text-base font-bold">Terapkan</button>
            <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base font-bold text-rose-700">Pratinjau PDF</a>
            <a href="{{ route('spj.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-base font-bold text-emerald-700">Unduh Excel</a>
            <a href="{{ route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" target="_blank" class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-base font-bold text-violet-700">Daftar Honor PDF</a>
            <a href="{{ route('spj.honor-payments.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-2.5 text-base font-bold text-sky-700">Daftar Honor Excel</a>
        </form>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Paket sukses', $summary['count'] ?? 0, 'text-indigo-700'],
            ['Paket dibatalkan', $summary['cancelled_count'] ?? 0, 'text-rose-700'],
            ['Nilai bruto sukses', $rupiah($summary['gross'] ?? 0), 'text-slate-800'],
            ['Nilai dibayarkan sukses', $rupiah($summary['net'] ?? 0), 'text-emerald-700'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow transition hover:shadow">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-xl font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto p-5">
        <table class="min-w-full divide-y divide-[var(--ui-line)] text-base">
            <thead class="bg-[var(--ui-surface-soft)]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">NOMOR SPJ</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">STATUS</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">BUKTI / TANGGAL</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500">PENERIMA</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500">BRUTO</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500">PAJAK</th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500">DIBAYARKAN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                @forelse($packages ?? [] as $package)
                    @php($isCancelled = $package->report_status === 'CANCELLED')
                    <tr class="transition {{ $isCancelled ? 'bg-rose-50/70 text-slate-500' : 'hover:bg-indigo-50/40' }}">
                        <td class="px-4 py-3 font-mono text-xs font-bold {{ $isCancelled ? 'text-rose-700 line-through' : 'text-indigo-700' }}"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="hover:underline">{{ $package->report_document_number }}</a></td>
                        <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isCancelled ? 'border border-rose-200 bg-rose-100 text-rose-800' : 'border border-emerald-200 bg-emerald-100 text-emerald-800' }}">{{ $isCancelled ? 'Dibatalkan' : 'Sukses' }}</span>@if($isCancelled && $package->report_cancellation_reason)<p class="mt-1 max-w-48 text-xs text-rose-700">{{ $package->report_cancellation_reason }}</p>@endif</td>
                        <td class="px-4 py-3"><p class="font-semibold">{{ $package->transaction->no_bukti }}</p><p class="text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td>
                        <td class="px-4 py-3">{{ $package->transaction->recipient_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $rupiah($package->transaction->gross_amount) }}</td>
                        <td class="px-4 py-3 text-right {{ $isCancelled ? 'text-slate-400' : 'text-amber-700' }}">{{ $rupiah($package->transaction->tax_total) }}</td>
                        <td class="px-4 py-3 text-right font-bold {{ $isCancelled ? 'text-slate-400' : 'text-emerald-700' }}">{{ $rupiah($package->transaction->net_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">Belum ada riwayat paket SPJ untuk filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
