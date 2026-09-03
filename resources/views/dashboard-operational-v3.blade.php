<x-layouts.tailwind-app>
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
                        <a href="{{ route('transactions.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm">Buka transaksi</a>
                        <a href="{{ route('spj.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white">Ruang Kerja SPJ</a>
                    </div>
                </div>
            </div>

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('transactions.index') }}" class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi aktif</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($summary['transactions'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Dalam konteks aktif</p>
                </a>
                <a href="{{ route('spj.index', ['tab' => 'persiapan']) }}" class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-600">Perlu dilengkapi</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($summary['without_package'] + $summary['draft'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Belum punya paket atau masih draft</p>
                </a>
                <a href="{{ route('spj.numbering-workflow') }}" class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-sky-600">Siap diproses</p>
                    <p class="mt-1 text-2xl font-bold text-sky-700">{{ number_format($summary['ready'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Paket siap masuk penomoran</p>
                </a>
                <a href="{{ route('reconciliation.index') }}" class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-orange-600">Perlu perhatian</p>
                    <p class="mt-1 text-2xl font-bold text-orange-700">{{ number_format($summary['reconciliation'] + $summary['source_missing'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Rekonsiliasi atau data sumber hilang</p>
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-500">Langkah berikutnya</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">Prioritas kerja yang disarankan</h2>
                <p class="mt-1 text-sm text-slate-500">Kerjakan dari urutan pertama agar pekerjaan operator lebih terarah.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($nextActions as $index => $action)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-slate-200 bg-slate-50 text-sm font-extrabold text-slate-700">{{ $index + 1 }}</span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $action['priority'] }}</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ $action['title'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $action['description'] }}</p>
                            </div>
                        </div>
                        <a href="{{ $action['url'] }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-800">{{ $action['action'] }} →</a>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.3fr_.7fr]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">Antrean kerja operator</h2>
                    <p class="mt-1 text-sm text-slate-500">Transaksi yang masih membutuhkan tindakan.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($workQueue as $transaction)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('transactions.show', $transaction->id) }}" class="font-mono text-sm font-bold theme-text">{{ $transaction->no_bukti ?: 'Tanpa nomor bukti' }}</a>
                                    @if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')
                                        <x-ui.status-badge status="SOURCE_MISSING" size="xs" />
                                    @elseif((bool) $transaction->requires_reconciliation)
                                        <x-ui.status-badge status="REQUIRES_RECONCILIATION" size="xs" />
                                    @elseif($transaction->spjPackage)
                                        <x-ui.status-badge :status="$transaction->spjPackage->status" size="xs" />
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-bold text-slate-700">Belum disiapkan</span>
                                    @endif
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian belum tersedia' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $transaction->items_count }} rincian · Rp {{ number_format((float) $transaction->gross_amount, 0, ',', '.') }}</p>
                            </div>
                            <a href="{{ route('transactions.show', $transaction->id) }}" class="inline-flex shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700">Lanjutkan →</a>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500">Tidak ada transaksi yang sedang menunggu tindakan operator.</div>
                    @endforelse
                </div>
            </article>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Progres paket SPJ</p>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl bg-sky-50 px-3 py-3"><p class="text-xl font-bold text-sky-700">{{ $summary['ready'] }}</p><p class="mt-1 text-[11px] font-semibold text-sky-800">Siap</p></div>
                        <div class="rounded-xl bg-indigo-50 px-3 py-3"><p class="text-xl font-bold text-indigo-700">{{ $summary['numbered'] }}</p><p class="mt-1 text-[11px] font-semibold text-indigo-800">Bernomor</p></div>
                        <div class="rounded-xl bg-emerald-50 px-3 py-3"><p class="text-xl font-bold text-emerald-700">{{ $summary['final'] }}</p><p class="mt-1 text-[11px] font-semibold text-emerald-800">Final</p></div>
                    </div>
                    <a href="{{ route('spj.index', ['tab' => 'paket']) }}" class="mt-4 inline-flex text-sm font-bold theme-text">Buka daftar paket →</a>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Status sinkronisasi</p>
                    @if($latestSync)
                        <div class="mt-2"><x-ui.status-badge :status="$latestSync->status" size="xs" /></div>
                    @else
                        <p class="mt-2 text-sm font-semibold text-slate-700">Belum pernah sinkronisasi</p>
                    @endif
                </section>
            </aside>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-bold text-slate-900">Kesiapan per triwulan</h2>
                <p class="mt-1 text-sm text-slate-500">Buka triwulan untuk melihat kesiapan penomoran.</p>
            </div>
            <div class="grid gap-px bg-slate-200 md:grid-cols-2 xl:grid-cols-4">
                @foreach($quarterSummary as $row)
                    <a href="{{ route('spj.numbering-workflow', ['quarter' => $row['quarter']]) }}" class="bg-white p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-bold text-slate-900">Triwulan {{ $row['quarter'] }}</h3>
                            @if($row['blocked'] > 0)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">{{ $row['blocked'] }} kendala</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Siap ditinjau</span>
                            @endif
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
                            <div><p class="text-xs text-slate-400">Transaksi</p><p class="font-bold text-slate-800">{{ $row['total'] }}</p></div>
                            <div><p class="text-xs text-slate-400">Siap</p><p class="font-bold text-sky-700">{{ $row['ready'] }}</p></div>
                            <div><p class="text-xs text-slate-400">Bernomor</p><p class="font-bold text-indigo-700">{{ $row['numbered'] }}</p></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
