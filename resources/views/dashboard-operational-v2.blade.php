<x-layouts.tailwind-app>
    @php
        $rupiah = function ($value) {
            return 'Rp ' . number_format((float) $value, 0, ',', '.');
        };
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-bold uppercase tracking-[.2em] text-sky-200">Dashboard operasional</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Apa yang perlu dikerjakan hari ini?</h1>
                        <p class="mt-2 text-sm leading-6 text-indigo-100 sm:text-base">Pantau antrean transaksi, kesiapan paket SPJ, rekonsiliasi, dan penomoran dari satu halaman kerja.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button href="{{ route('transactions.index') }}">Buka transaksi</x-ui.button>
                        <a href="{{ route('spj.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">Ruang Kerja SPJ</a>
                    </div>
                </div>
            </div>

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('transactions.index') }}" class="bg-white px-5 py-4 transition hover:bg-slate-50">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi aktif</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($summary['transactions'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Dalam konteks tahun & sumber dana aktif</p>
                </a>
                <a href="{{ route('spj.index', ['tab' => 'persiapan']) }}" class="bg-white px-5 py-4 transition hover:bg-amber-50/50">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-600">Perlu dilengkapi</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($summary['without_package'] + $summary['draft'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Belum punya paket atau masih belum lengkap</p>
                </a>
                <a href="{{ route('spj.numbering-workflow') }}" class="bg-white px-5 py-4 transition hover:bg-sky-50/50">
                    <p class="text-xs font-bold uppercase tracking-wide text-sky-600">Siap diproses</p>
                    <p class="mt-1 text-2xl font-bold text-sky-700">{{ number_format($summary['ready'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Paket siap masuk penomoran</p>
                </a>
                <a href="{{ route('reconciliation.index') }}" class="bg-white px-5 py-4 transition hover:bg-orange-50/50">
                    <p class="text-xs font-bold uppercase tracking-wide text-orange-600">Perlu perhatian</p>
                    <p class="mt-1 text-2xl font-bold text-orange-700">{{ number_format($summary['reconciliation'] + $summary['source_missing'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Perubahan sumber atau data tidak muncul lagi</p>
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-500">Langkah berikutnya</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Prioritas kerja yang disarankan</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Paling penting lebih dahulu</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">Tindakan disusun dari kondisi sinkronisasi, rekonsiliasi, kelengkapan paket, dan kesiapan penomoran.</p>
            </div>

            <div class="grid gap-3 p-4 lg:grid-cols-2 lg:p-5">
                @foreach($nextActions as $index => $action)
                    <article @class([
                        'rounded-2xl border p-4',
                        'border-rose-200 bg-rose-50/70' => $action['tone'] === 'rose',
                        'border-orange-200 bg-orange-50/70' => $action['tone'] === 'orange',
                        'border-amber-200 bg-amber-50/70' => $action['tone'] === 'amber',
                        'border-sky-200 bg-sky-50/70' => $action['tone'] === 'sky',
                        'border-emerald-200 bg-emerald-50/70' => $action['tone'] === 'emerald',
                    ])>
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-white/70 bg-white text-sm font-extrabold text-slate-700 shadow-sm">{{ $index + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <span @class([
                                    'inline-flex rounded-full border bg-white px-2.5 py-1 text-[11px] font-bold',
                                    'border-rose-200 text-rose-700' => $action['tone'] === 'rose',
                                    'border-orange-200 text-orange-700' => $action['tone'] === 'orange',
                                    'border-amber-200 text-amber-700' => $action['tone'] === 'amber',
                                    'border-sky-200 text-sky-700' => $action['tone'] === 'sky',
                                    'border-emerald-200 text-emerald-700' => $action['tone'] === 'emerald',
                                ])>{{ $action['priority'] }}</span>
                                <h3 class="mt-2 font-bold leading-6 text-slate-900">{{ $action['title'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $action['description'] }}</p>
                                <a href="{{ $action['url'] }}" @class([
                                    'mt-4 inline-flex min-h-10 items-center justify-center rounded-lg px-3.5 py-2 text-sm font-bold text-white shadow-sm transition',
                                    'bg-rose-600 hover:bg-rose-700' => $action['tone'] === 'rose',
                                    'bg-orange-600 hover:bg-orange-700' => $action['tone'] === 'orange',
                                    'bg-amber-600 hover:bg-amber-700' => $action['tone'] === 'amber',
                                    'bg-sky-600 hover:bg-sky-700' => $action['tone'] === 'sky',
                                    'bg-emerald-600 hover:bg-emerald-700' => $action['tone'] === 'emerald',
                                ])>{{ $action['action'] }} →</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.3fr_.7fr]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-bold text-slate-900">Antrean kerja operator</h2>
                        <p class="mt-1 text-sm text-slate-500">Prioritas transaksi yang perlu ditinjau atau dilanjutkan.</p>
                    </div>
                    @if($attentionCount > 0)
                        <span class="inline-flex w-fit rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-bold text-orange-800">{{ $attentionCount }} pekerjaan perlu perhatian</span>
                    @else
                        <span class="inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">Tidak ada kendala utama</span>
                    @endif
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($workQueue as $transaction)
                        @php
                            $status = $transaction->spjPackage ? $transaction->spjPackage->status : null;
                            $isMissing = strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING';
                            $needsReconciliation = (bool) $transaction->requires_reconciliation;
                        @endphp
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('transactions.show', $transaction->id) }}" class="font-mono text-sm font-bold theme-text">{{ $transaction->no_bukti ?: 'Tanpa nomor bukti' }}</a>
                                    @if($isMissing)
                                        <x-ui.status-badge status="SOURCE_MISSING" size="xs" />
                                    @elseif($needsReconciliation)
                                        <x-ui.status-badge status="REQUIRES_RECONCILIATION" size="xs" />
                                    @elseif($status)
                                        <x-ui.status-badge :status="$status" size="xs" />
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-bold text-slate-700">Belum disiapkan</span>
                                    @endif
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian belum tersedia' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date ? $transaction->transaction_date->translatedFormat('d F Y') : '—' }} · {{ $transaction->items_count }} rincian · {{ $rupiah($transaction->gross_amount) }}</p>
                            </div>
                            <a href="{{ $isMissing || $needsReconciliation ? route('reconciliation.index') : route('transactions.show', $transaction->id) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">{{ $isMissing || $needsReconciliation ? 'Tinjau rekonsiliasi' : 'Lanjutkan' }} →</a>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-800">Antrean kerja kosong.</p>
                            <p class="mt-1 text-sm text-slate-500">Tidak ada transaksi yang sedang menunggu tindakan operator.</p>
                        </div>
                    @endforelse
                </div>
            </article>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Status sinkronisasi</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $latestSync && $latestSync->status === 'SUCCESS' ? 'Berhasil' : ($latestSync && $latestSync->status ? str($latestSync->status)->replace('_', ' ')->title() : 'Belum pernah') }}</p>
                        </div>
                        @if($latestSync && $latestSync->status)
                            <x-ui.status-badge :status="$latestSync->status" size="xs" />
                        @endif
                    </div>
                    <p class="mt-3 text-sm text-slate-500">Terakhir: {{ $latestSync && $latestSync->finished_at ? \Illuminate\Support\Carbon::parse($latestSync->finished_at)->translatedFormat('d F Y H:i') : '—' }}</p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Progres paket SPJ</p>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-sky-50 px-3 py-3"><p class="text-xl font-bold text-sky-700">{{ $summary['ready'] }}</p><p class="mt-1 text-[11px] font-semibold text-sky-800">Siap</p></div>
                        <div class="rounded-xl bg-indigo-50 px-3 py-3"><p class="text-xl font-bold text-indigo-700">{{ $summary['numbered'] }}</p><p class="mt-1 text-[11px] font-semibold text-indigo-800">Bernomor</p></div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-3"><p class="text-xl font-bold text-emerald-700">{{ $summary['final'] }}</p><p class="mt-1 text-[11px] font-semibold text-emerald-800">Final</p></div>
                    </div>
                    <a href="{{ route('spj.index', ['tab' => 'paket']) }}" class="mt-4 inline-flex text-sm font-bold theme-text hover:underline">Buka daftar paket →</a>
                </section>

                @if($latestOperation && in_array($latestOperation->status, ['QUEUED', 'RUNNING', 'FAILED'], true))
                    <section class="rounded-2xl border p-5 {{ $latestOperation->status === 'FAILED' ? 'border-rose-200 bg-rose-50' : 'border-sky-200 bg-sky-50' }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-bold {{ $latestOperation->status === 'FAILED' ? 'text-rose-800' : 'text-sky-800' }}">Proses latar belakang</p>
                            <x-ui.status-badge :status="$latestOperation->status" size="xs" />
                        </div>
                        <p class="mt-2 text-sm {{ $latestOperation->status === 'FAILED' ? 'text-rose-700' : 'text-sky-700' }}">{{ $latestOperation->message }}</p>
                    </section>
                @endif
            </aside>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-bold text-slate-900">Kesiapan per triwulan</h2>
                <p class="mt-1 text-sm text-slate-500">Ringkasan cepat untuk menentukan kapan penomoran dapat dijalankan.</p>
            </div>
            <div class="grid gap-px bg-slate-200 md:grid-cols-2 xl:grid-cols-4">
                @foreach($quarterSummary as $row)
                    <a href="{{ route('spj.numbering-workflow', ['quarter' => $row['quarter']]) }}" class="bg-white p-5 transition hover:bg-slate-50">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-bold text-slate-900">Triwulan {{ $row['quarter'] }}</h3>
                            @if($row['blocked'] > 0)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">{{ $row['blocked'] }} kendala</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Siap ditinjau</span>
                            @endif
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div><dt class="text-[11px] text-slate-400">Transaksi</dt><dd class="mt-1 font-bold text-slate-800">{{ $row['total'] }}</dd></div>
                            <div><dt class="text-[11px] text-slate-400">Siap</dt><dd class="mt-1 font-bold text-sky-700">{{ $row['ready'] }}</dd></div>
                            <div><dt class="text-[11px] text-slate-400">Bernomor</dt><dd class="mt-1 font-bold text-indigo-700">{{ $row['numbered'] }}</dd></div>
                        </dl>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('transactions.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Keuangan</p><h2 class="mt-2 font-bold text-slate-900">Transaksi</h2><p class="mt-1 text-sm text-slate-500">Cari transaksi dan lengkapi data SPJ operator.</p></a>
            <a href="{{ route('reconciliation.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-xs font-bold uppercase tracking-wide text-orange-500">Kontrol</p><h2 class="mt-2 font-bold text-slate-900">Rekonsiliasi</h2><p class="mt-1 text-sm text-slate-500">Periksa perubahan atau kehilangan data sumber.</p></a>
            <a href="{{ route('spj.numbering-workflow') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-xs font-bold uppercase tracking-wide text-indigo-500">Dokumen</p><h2 class="mt-2 font-bold text-slate-900">Penomoran SPJ</h2><p class="mt-1 text-sm text-slate-500">Preview kesiapan triwulan sebelum menetapkan nomor.</p></a>
            <a href="{{ route('dashboard.v2') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><p class="text-xs font-bold uppercase tracking-wide text-violet-500">Analitik</p><h2 class="mt-2 font-bold text-slate-900">Dashboard v.2</h2><p class="mt-1 text-sm text-slate-500">Buka dashboard lama dengan grafik dan ringkasan anggaran.</p></a>
        </section>
    </div>
</x-layouts.tailwind-app>
