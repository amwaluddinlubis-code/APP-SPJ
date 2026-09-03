<x-layouts.tailwind-app>
    <style>
        .audit-panel { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; box-shadow: 0 1px 3px rgb(15 23 42 / .08); }
        .audit-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #f1f5f9; padding: 1.25rem 1.5rem; }
        .audit-panel-heading h2 { color: #1e293b; font-size: .95rem; font-weight: 700; }
        .audit-panel-heading p { margin-top: .25rem; color: #64748b; font-size: .8rem; }
        .audit-table-wrap { width: 100%; overflow-x: auto; }
        .audit-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        .audit-table.reconciliation-table { min-width: 1080px; }
        .audit-table.register-table, .audit-table.completeness-table { min-width: 900px; }
        .audit-table.history-table { min-width: 780px; }
        .audit-table thead { background: #f8fafc; color: #64748b; }
        .audit-table th { white-space: nowrap; padding: .75rem 1rem; text-align: left; font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .audit-table td { border-top: 1px solid #f1f5f9; padding: .7rem 1rem; vertical-align: top; white-space: nowrap; }
        .audit-table th:first-child, .audit-table td:first-child { padding-left: 1.5rem; }
        .audit-table th:last-child, .audit-table td:last-child { padding-right: 1.5rem; }
        .audit-table td.wrap { white-space: normal; overflow-wrap: anywhere; }
        .audit-panel > nav { border-top: 1px solid #f1f5f9; padding: 1rem 1.5rem; }
        @media (max-width: 640px) {
            .audit-panel-heading { align-items: flex-start; padding: 1rem; }
            .audit-table th, .audit-table td { padding: .55rem .75rem; }
            .audit-table th:first-child, .audit-table td:first-child { padding-left: 1rem; }
            .audit-table th:last-child, .audit-table td:last-child { padding-right: 1rem; }
        }
    </style>
    @php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))
    <div x-data="{ tab: @js(request('tab', 'overview')) }" class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-bold tracking-[.16em] text-sky-200">AUDIT REPORTING SUITE</p>
                        <h1 class="mt-2 text-2xl font-bold">Laporan Audit</h1>
                        <p class="mt-1 text-sm text-slate-300">Pilih tab laporan untuk meninjau data secara lebih terarah.</p>
                        <p class="mt-2 text-xs font-semibold text-sky-100">{{ $year->year }} · {{ $fundSource?->name ?? $year->fund_source }} · ID {{ session('active_fund_source_id') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('audit-reports.export', 'xlsx') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-800 shadow">Unduh XLSX</a>
                        <a href="{{ route('audit-reports.export', 'pdf') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-inset ring-white/30">Cetak PDF</a>
                    </div>
                </div>
            </div>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">RKAS</p><p class="mt-1 text-xl font-bold text-indigo-700">{{ $rupiah($summary['budget']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">BKU Belanja</p><p class="mt-1 text-xl font-bold text-emerald-700">{{ $rupiah($summary['bku']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi Unik</p><p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($summary['transactionCount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ $rupiah($summary['transactions']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Temuan SPJ</p><p class="mt-1 text-xl font-bold {{ $summary['exceptionCount'] ? 'text-rose-600' : 'text-emerald-700' }}">{{ number_format($summary['exceptionCount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">{{ $summary['spjNumbered'] }} bernomor / {{ $summary['spjPackaged'] }} paket</p></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
            <nav class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6" aria-label="Tab laporan audit">
                @foreach([
                    'overview' => ['Ringkasan', 'dashboard'],
                    'reconciliation' => ['Rekonsiliasi', 'report'],
                    'register' => ['Buku Kas', 'transaction'],
                    'tax' => ['Pajak', 'tax'],
                    'completeness' => ['Kelengkapan SPJ', 'document'],
                    'history' => ['Riwayat', 'sync'],
                ] as $key => [$label, $icon])
                    <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100'" class="flex items-center justify-center gap-2 rounded-xl px-3 py-3 text-sm font-bold transition">
                        <x-ui-icon name="{{ $icon }}" /> <span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>
        </section>

        <section x-show="tab === 'overview'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-5">
                <h2 class="font-bold text-amber-900">Batasan data audit</h2>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-amber-800">@foreach($limitations as $limitation)<li>{{ $limitation }}</li>@endforeach</ul>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm">
                <h2 class="font-bold text-slate-800">Indikator pemeriksaan</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">Selisih rekonsiliasi</span><strong class="mt-1 block text-xl text-rose-600">{{ $summary['mismatchCount'] }}</strong></div>
                    <div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">Paket SPJ</span><strong class="mt-1 block text-xl text-indigo-600">{{ $summary['spjPackaged'] }}</strong></div>
                    <div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">Sinkronisasi</span><strong class="mt-1 block text-xl text-emerald-600">{{ $summary['syncCount'] }}</strong></div>
                    <div class="rounded-xl bg-slate-50 p-4"><span class="text-slate-500">Aktivitas audit</span><strong class="mt-1 block text-xl text-slate-700">{{ $summary['auditCount'] }}</strong></div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'reconciliation'" x-cloak class="audit-panel">
            <div class="audit-panel-heading"><div><h2>Rekonsiliasi RKAS · BKU · Transaksi · SPJ</h2><p>Satu baris per nomor bukti. {{ $summary['mismatchCount'] }} baris perlu ditinjau.</p></div></div>
            <div class="audit-table-wrap"><table class="audit-table reconciliation-table"><thead><tr><th>No Bukti</th><th>Tanggal</th><th class="text-right">RKAS</th><th class="text-right">BKU</th><th class="text-right">Transaksi</th><th class="text-right">Selisih</th><th>SPJ</th><th>Status</th></tr></thead><tbody>@forelse($reconciliationRows as $row)<tr><td class="font-mono font-bold text-indigo-700">{{ $row->no_bukti }}</td><td>{{ optional($row->transaction_date)->translatedFormat('d F Y') ?: '-' }}</td><td class="text-right">{{ $rupiah($row->rkas_amount) }}</td><td class="text-right">{{ $rupiah($row->bku_amount) }}</td><td class="text-right">{{ $rupiah($row->transaction_amount) }}</td><td class="text-right font-semibold {{ abs($row->variance) > .01 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $rupiah($row->variance) }}</td><td>{{ $row->spj_status }}</td><td><span class="rounded-full px-2 py-1 text-xs font-bold {{ $row->status === 'SESUAI' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $row->status }}</span></td></tr>@empty<tr><td colspan="8" class="empty-cell">Belum ada data rekonsiliasi.</td></tr>@endforelse</tbody></table></div>{{ $reconciliationRows->links() }}
        </section>

        <section x-show="tab === 'register'" x-cloak class="audit-panel">
            <div class="audit-panel-heading"><div><h2>Buku Kas / Register Transaksi</h2><p>{{ number_format($summary['transactionCount'], 0, ',', '.') }} transaksi unik pada konteks aktif.</p></div></div>
            <div class="audit-table-wrap"><table class="audit-table register-table"><thead><tr><th>Bukti / Tanggal</th><th>Uraian / Penerima</th><th>Kegiatan / Rekening</th><th class="text-right">Bruto</th><th class="text-right">Pajak</th><th>SPJ</th></tr></thead><tbody>@forelse($register as $transaction)<tr><td><a class="font-mono font-bold text-indigo-700" href="{{ route('transactions.show', $transaction->id) }}">{{ $transaction->no_bukti }}</a><p class="text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?: '-' }}</p></td><td><p class="max-w-xs truncate font-semibold">{{ $transaction->description ?: '-' }}</p><p class="max-w-xs truncate text-xs text-slate-500">{{ $transaction->recipient_name ?: '-' }}</p></td><td><p class="font-mono text-xs">{{ $transaction->activity_code ?: '-' }}</p><p class="text-xs text-slate-500">{{ $transaction->account_code ?: 'Belum diisi' }}</p></td><td class="text-right">{{ $rupiah($transaction->gross_amount) }}</td><td class="text-right">{{ $rupiah($transaction->tax_total) }}</td><td>{{ $transaction->spjPackage?->document_number ?: ($transaction->spjPackage ? 'DRAFT' : 'BELUM ADA') }}</td></tr>@empty<tr><td colspan="6" class="empty-cell">Belum ada transaksi tersinkron.</td></tr>@endforelse</tbody></table></div>{{ $register->links() }}
        </section>

        <section x-show="tab === 'tax'" x-cloak class="audit-panel">
            <div class="audit-panel-heading"><div><h2>Rekap Pajak Sinkronisasi</h2><p>Dihitung dari kolom pajak transaksi unik.</p></div></div>
            <div class="audit-table-wrap"><table class="audit-table tax-table"><thead><tr><th>Jenis</th><th class="text-right">Transaksi</th><th class="text-right">Nominal</th></tr></thead><tbody>@foreach($taxSummary as $row)<tr><td>{{ $row->label }}</td><td class="text-right">{{ $row->count }}</td><td class="text-right font-semibold">{{ $rupiah($row->amount) }}</td></tr>@endforeach</tbody></table></div>
        </section>

        <section x-show="tab === 'completeness'" x-cloak class="audit-panel">
            <div class="audit-panel-heading"><div><h2>Kelengkapan / Pengecualian SPJ</h2><p>Daftar transaksi yang perlu ditindaklanjuti sebelum dokumen dianggap lengkap.</p></div></div>
            <div class="audit-table-wrap"><table class="audit-table completeness-table"><thead><tr><th>No Bukti</th><th>Tanggal</th><th>Penerima</th><th class="text-right">Bruto</th><th>Status</th><th>Temuan</th></tr></thead><tbody>@forelse($completenessRows as $row)<tr><td class="font-mono font-bold">{{ $row->no_bukti }}</td><td>{{ optional($row->transaction_date)->translatedFormat('d F Y') ?: '-' }}</td><td>{{ $row->recipient_name ?: '-' }}</td><td class="text-right">{{ $rupiah($row->amount) }}</td><td class="{{ $row->status === 'LENGKAP' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $row->status }}</td><td class="wrap text-xs">{{ $row->issues ? implode('; ', $row->issues) : '-' }}</td></tr>@empty<tr><td colspan="6" class="empty-cell">Tidak ada data pengecualian.</td></tr>@endforelse</tbody></table></div>{{ $completenessRows->links() }}
        </section>

        <section x-show="tab === 'history'" x-cloak class="audit-panel">
            <div class="audit-panel-heading"><div><h2>Riwayat Sinkronisasi & Operasional</h2><p>{{ $summary['syncCount'] }} sinkronisasi dan {{ $summary['auditCount'] }} aktivitas.</p></div></div>
            <div class="audit-table-wrap"><table class="audit-table history-table"><thead><tr><th>Jenis</th><th>Status / Aksi</th><th>Waktu</th><th>Keterangan</th></tr></thead><tbody>@forelse($syncRuns as $row)<tr><td>SINKRONISASI {{ $row->source }}</td><td>{{ $row->status }}</td><td>{{ $row->started_at }}</td><td>{{ $row->message ?: 'Data dibaca: '.$row->records_read.' · ditulis: '.$row->records_written }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">Belum ada riwayat sinkronisasi.</td></tr>@endforelse @foreach($auditLogs as $row)<tr><td>{{ $row->entity_type }}</td><td>{{ $row->action }}</td><td>{{ $row->created_at }}</td><td>{{ $row->description }}</td></tr>@endforeach</tbody></table></div>{{ $syncRuns->links() }}
        </section>
    </div>
</x-layouts.tailwind-app>
