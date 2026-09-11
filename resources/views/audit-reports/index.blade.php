<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))
    <div x-data="{ tab: @js(request('tab', 'overview')) }" class="space-y-6">
        <x-page-header
            title="Laporan Audit"
            subtitle="Pilih tab laporan untuk meninjau data secara lebih terarah."
            kicker="Audit Reporting Suite"
        >
            <x-slot:actions>
                <a href="{{ route('audit-reports.export', 'xlsx') }}" class="ui-btn ui-btn-secondary px-4 py-2.5 text-sm">Unduh XLSX</a>
                <a href="{{ route('audit-reports.export', 'pdf') }}" class="ui-btn ui-btn-primary px-4 py-2.5 text-sm">Cetak PDF</a>
            </x-slot:actions>

            <p class="text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $year->year }} · {{ $fundSource?->name ?? $year->fund_source }} · ID {{ session('active_fund_source_id') }}</p>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">RKAS</p><p class="mt-1 text-xl font-bold text-[var(--theme-content-accent)]">{{ $rupiah($summary['budget']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">BKU Belanja</p><p class="mt-1 text-xl font-bold text-emerald-700">{{ $rupiah($summary['bku']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Transaksi Unik</p><p class="mt-1 text-xl font-bold text-[var(--ui-fg-strong)]">{{ number_format($summary['transactionCount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $rupiah($summary['transactions']) }}</p></div>
                <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Temuan SPJ</p><p class="mt-1 text-xl font-bold {{ $summary['exceptionCount'] ? 'text-rose-600' : 'text-emerald-700' }}">{{ number_format($summary['exceptionCount'], 0, ',', '.') }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $summary['spjNumbered'] }} bernomor / {{ $summary['spjPackaged'] }} paket</p></div>
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-2 shadow-sm">
            <nav class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6" aria-label="Tab laporan audit">
                @foreach([
                    'overview' => ['Ringkasan', 'dashboard'],
                    'reconciliation' => ['Rekonsiliasi', 'report'],
                    'register' => ['Buku Kas', 'transaction'],
                    'tax' => ['Pajak', 'tax'],
                    'completeness' => ['Kelengkapan SPJ', 'document'],
                    'history' => ['Riwayat', 'sync'],
                ] as $key => [$label, $icon])
                    <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'ui-btn-primary' : 'text-[var(--ui-fg)] hover:bg-[var(--ui-surface-soft)]'" class="ui-btn flex items-center justify-center gap-2 px-3 py-3 text-sm">
                        <x-ui.icon :name="$icon" size="sm" /> <span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>
        </section>

        <section x-show="tab === 'overview'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-ui.alert type="warning" title="Batasan data audit" class="items-start">
                <ul class="list-disc space-y-2 pl-5 text-sm">@foreach($limitations as $limitation)<li>{{ $limitation }}</li>@endforeach</ul>
            </x-ui.alert>
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-5 py-5 shadow-sm">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Indikator pemeriksaan</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-[var(--ui-surface-soft)] p-4"><span class="text-[var(--ui-fg-muted)]">Selisih rekonsiliasi</span><strong class="mt-1 block text-xl text-rose-600">{{ $summary['mismatchCount'] }}</strong></div>
                    <div class="rounded-xl bg-[var(--ui-surface-soft)] p-4"><span class="text-[var(--ui-fg-muted)]">Paket SPJ</span><strong class="mt-1 block text-xl text-[var(--theme-content-accent)]">{{ $summary['spjPackaged'] }}</strong></div>
                    <div class="rounded-xl bg-[var(--ui-surface-soft)] p-4"><span class="text-[var(--ui-fg-muted)]">Sinkronisasi</span><strong class="mt-1 block text-xl text-emerald-600">{{ $summary['syncCount'] }}</strong></div>
                    <div class="rounded-xl bg-[var(--ui-surface-soft)] p-4"><span class="text-[var(--ui-fg-muted)]">Aktivitas audit</span><strong class="mt-1 block text-xl text-[var(--ui-fg-strong)]">{{ $summary['auditCount'] }}</strong></div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'reconciliation'" x-cloak class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6"><h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Rekonsiliasi RKAS · BKU · Transaksi · SPJ</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Satu baris per nomor bukti. {{ $summary['mismatchCount'] }} baris perlu ditinjau.</p></div>
            <x-ui.table min-width="1080px" pagination="external">
                <thead><tr><th>No Bukti</th><th>Tanggal</th><th class="text-right">RKAS</th><th class="text-right">BKU</th><th class="text-right">Transaksi</th><th class="text-right">Selisih</th><th>SPJ</th><th>Status</th></tr></thead>
                <tbody>@forelse($reconciliationRows as $row)<tr><td class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $row->no_bukti }}</td><td>{{ optional($row->transaction_date)->translatedFormat('d F Y') ?: '-' }}</td><td class="text-right">{{ $rupiah($row->rkas_amount) }}</td><td class="text-right">{{ $rupiah($row->bku_amount) }}</td><td class="text-right">{{ $rupiah($row->transaction_amount) }}</td><td class="text-right font-semibold {{ abs($row->variance) > .01 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $rupiah($row->variance) }}</td><td>{{ $row->spj_status }}</td><td><span class="rounded-full px-2 py-1 text-xs font-bold {{ $row->status === 'SESUAI' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $row->status }}</span></td></tr>@empty<tr><td colspan="8" class="empty-cell">Belum ada data rekonsiliasi.</td></tr>@endforelse</tbody>
            </x-ui.table>
            <div class="border-t border-[var(--ui-line)] px-5 py-4 sm:px-6">{{ $reconciliationRows->links() }}</div>
        </section>

        <section x-show="tab === 'register'" x-cloak class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6"><h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Buku Kas / Register Transaksi</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ number_format($summary['transactionCount'], 0, ',', '.') }} transaksi unik pada konteks aktif.</p></div>
            <x-ui.table min-width="900px" pagination="external">
                <thead><tr><th>Bukti / Tanggal</th><th>Uraian / Penerima</th><th>Kegiatan / Rekening</th><th class="text-right">Bruto</th><th class="text-right">Pajak</th><th>SPJ</th></tr></thead>
                <tbody>@forelse($register as $transaction)<tr><td><a class="font-mono font-bold text-[var(--theme-content-accent)]" href="{{ route('transactions.show', $transaction->id) }}">{{ $transaction->no_bukti }}</a><p class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?: '-' }}</p></td><td><p class="max-w-xs truncate font-semibold">{{ $transaction->description ?: '-' }}</p><p class="max-w-xs truncate text-xs text-[var(--ui-fg-muted)]">{{ $transaction->recipient_name ?: '-' }}</p></td><td><p class="font-mono text-xs">{{ $transaction->activity_code ?: '-' }}</p><p class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->account_code ?: 'Belum diisi' }}</p></td><td class="text-right">{{ $rupiah($transaction->gross_amount) }}</td><td class="text-right">{{ $rupiah($transaction->tax_total) }}</td><td>{{ $transaction->spjPackage?->document_number ?: ($transaction->spjPackage ? 'DRAFT' : 'BELUM ADA') }}</td></tr>@empty<tr><td colspan="6" class="empty-cell">Belum ada transaksi tersinkron.</td></tr>@endforelse</tbody>
            </x-ui.table>
            <div class="border-t border-[var(--ui-line)] px-5 py-4 sm:px-6">{{ $register->links() }}</div>
        </section>

        <section x-show="tab === 'tax'" x-cloak class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6"><h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Rekap Pajak Sinkronisasi</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Dihitung dari kolom pajak transaksi unik.</p></div>
            <x-ui.table pagination="none">
                <thead><tr><th>Jenis</th><th class="text-right">Transaksi</th><th class="text-right">Nominal</th></tr></thead>
                <tbody>@foreach($taxSummary as $row)<tr><td>{{ $row->label }}</td><td class="text-right">{{ $row->count }}</td><td class="text-right font-semibold">{{ $rupiah($row->amount) }}</td></tr>@endforeach</tbody>
            </x-ui.table>
        </section>

        <section x-show="tab === 'completeness'" x-cloak class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6"><h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Kelengkapan / Pengecualian SPJ</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Daftar transaksi yang perlu ditindaklanjuti sebelum dokumen dianggap lengkap.</p></div>
            <x-ui.table min-width="900px" pagination="external">
                <thead><tr><th>No Bukti</th><th>Tanggal</th><th>Penerima</th><th class="text-right">Bruto</th><th>Status</th><th>Temuan</th></tr></thead>
                <tbody>@forelse($completenessRows as $row)<tr><td class="font-mono font-bold">{{ $row->no_bukti }}</td><td>{{ optional($row->transaction_date)->translatedFormat('d F Y') ?: '-' }}</td><td>{{ $row->recipient_name ?: '-' }}</td><td class="text-right">{{ $rupiah($row->amount) }}</td><td class="{{ $row->status === 'LENGKAP' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $row->status }}</td><td class="whitespace-normal text-xs [overflow-wrap:anywhere]">{{ $row->issues ? implode('; ', $row->issues) : '-' }}</td></tr>@empty<tr><td colspan="6" class="empty-cell">Tidak ada data pengecualian.</td></tr>@endforelse</tbody>
            </x-ui.table>
            <div class="border-t border-[var(--ui-line)] px-5 py-4 sm:px-6">{{ $completenessRows->links() }}</div>
        </section>

        <section x-show="tab === 'history'" x-cloak class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6"><h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">Riwayat Sinkronisasi & Operasional</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $summary['syncCount'] }} sinkronisasi dan {{ $summary['auditCount'] }} aktivitas.</p></div>
            <x-ui.table min-width="780px" pagination="external">
                <thead><tr><th>Jenis</th><th>Status / Aksi</th><th>Waktu</th><th>Keterangan</th></tr></thead>
                <tbody>@forelse($syncRuns as $row)<tr><td>SINKRONISASI {{ $row->source }}</td><td>{{ $row->status }}</td><td>{{ $row->started_at }}</td><td>{{ $row->message ?: 'Data dibaca: '.$row->records_read.' · ditulis: '.$row->records_written }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">Belum ada riwayat sinkronisasi.</td></tr>@endforelse @foreach($auditLogs as $row)<tr><td>{{ $row->entity_type }}</td><td>{{ $row->action }}</td><td>{{ $row->created_at }}</td><td>{{ $row->description }}</td></tr>@endforeach</tbody>
            </x-ui.table>
            <div class="border-t border-[var(--ui-line)] px-5 py-4 sm:px-6">{{ $syncRuns->links() }}</div>
        </section>
    </div>
</x-layouts.tailwind-app>
