<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.'))
    @php($missingCount = $missingRequired->count())
    @php($readyCount = (int) ($summary['required_ready'] ?? 0))
    @php($requiredTotal = (int) ($summary['required_total'] ?? 0))
    @php($progress = $requiredTotal > 0 ? (int) round(($readyCount / $requiredTotal) * 100) : 100)

    <div class="space-y-6">
        <x-page-header
            title="Siapkan SPJ"
            subtitle="Aplikasi hanya menampilkan dokumen wajib yang masih kurang. Lengkapi bagian ini terlebih dahulu, lalu lanjutkan ke paket SPJ."
            kicker="Persiapan transaksi"
        >
            <x-slot:actions>
                <a href="{{ route('transactions.show', $transaction->id) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white hover:bg-white/20">Lihat detail transaksi</a>
                @if($transaction->spjPackage)
                    <a href="{{ route('spj.checklist', $transaction->spjPackage->id) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-indigo-950 shadow">Buka checklist paket</a>
                @endif
            </x-slot:actions>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">No. bukti</p>
                    <p class="mt-1 font-mono text-lg font-extrabold text-slate-900">{{ $transaction->no_bukti ?: '-' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $transaction->transaction_date?->translatedFormat('d F Y') ?: '-' }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Jalur transaksi</p>
                    <p class="mt-1 text-lg font-extrabold text-indigo-700">{{ $summary['channel'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Kategori: {{ str_replace('_', ' ', (string) $transaction->spj_category) }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Masih kurang</p>
                    <p class="mt-1 text-2xl font-extrabold {{ $missingCount ? 'text-amber-700' : 'text-emerald-700' }}">{{ $missingCount }}</p>
                    <p class="mt-1 text-xs text-slate-500">dari {{ $requiredTotal }} dokumen/data wajib</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Kesiapan</p>
                        <span class="text-sm font-bold text-indigo-700">{{ $progress }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ $readyCount }} dari {{ $requiredTotal }} siap</p>
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-slate-50 px-5 py-4 sm:px-6">
                    <h2 class="font-extrabold tracking-tight text-slate-900">Yang perlu dilengkapi sekarang</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Bagian yang sudah lengkap disembunyikan agar operator bisa fokus pada pekerjaan yang tersisa.</p>
                </div>

                @if($missingCount > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach($missingRequired as $item)
                            <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                <div class="flex min-w-0 gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-amber-100 text-sm font-black text-amber-700">!</span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $item['group'] }}</p>
                                        <h3 class="mt-0.5 font-extrabold text-slate-900">{{ $item['label'] }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-amber-800">{{ $item['message'] }}</p>
                                        <p class="mt-2 text-xs font-semibold text-slate-400">Sumber: {{ $item['source'] }}</p>
                                    </div>
                                </div>
                                <a href="{{ $actionUrl($item) }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">Lengkapi sekarang →</a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 py-10 text-center sm:px-6">
                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-100 text-xl font-black text-emerald-700">✓</span>
                        <h3 class="mt-4 text-lg font-extrabold text-emerald-950">Semua dokumen wajib sudah siap</h3>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-emerald-800">Tidak ada data wajib yang masih kurang. Anda dapat melanjutkan ke paket SPJ dan pemeriksaan akhir.</p>
                    </div>
                @endif
            </article>

            <aside class="space-y-4">
                <section class="rounded-2xl border {{ $missingCount ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-5 shadow-sm">
                    @if($missingCount)
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-600">Fokus sekarang</p>
                        <h2 class="mt-2 text-lg font-extrabold text-amber-950">Selesaikan {{ $missingCount }} bagian</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-800">Setelah menyimpan perubahan pada Detail Transaksi, kembali ke halaman ini. Daftar akan diperbarui otomatis dari data terbaru.</p>
                    @else
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-600">Siap dilanjutkan</p>
                        <h2 class="mt-2 text-lg font-extrabold text-emerald-950">Persiapan transaksi selesai</h2>
                        @if($transaction->spjPackage)
                            <p class="mt-2 text-sm leading-6 text-emerald-800">Paket SPJ sudah tersedia. Lanjutkan ke checklist paket sebelum penomoran.</p>
                            <a href="{{ route('spj.checklist', $transaction->spjPackage->id) }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-800">Periksa paket SPJ →</a>
                        @else
                            <p class="mt-2 text-sm leading-6 text-emerald-800">Data wajib sudah lengkap. Buka bagian pembuatan SPJ pada transaksi untuk membuat paket.</p>
                            <a href="{{ route('transactions.show', $transaction->id).'#modul-buat-spj' }}" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-800">Buat paket SPJ →</a>
                        @endif
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Transaksi</p>
                    <p class="mt-2 text-sm font-extrabold text-slate-900">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian belum tersedia' }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Penerima</dt><dd class="text-right font-semibold text-slate-800">{{ $transaction->effective_receipt_recipient_name ?: '-' }}</dd></div>
                        <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Nilai</dt><dd class="font-extrabold text-slate-900">{{ $rupiah($transaction->gross_amount) }}</dd></div>
                        <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Cara bayar</dt><dd class="font-semibold text-slate-800">{{ strtoupper(str_replace('_', ' ', (string) $transaction->payment_method)) ?: '-' }}</dd></div>
                    </dl>
                </section>

                @if($optionalIncomplete->isNotEmpty())
                    <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <summary class="cursor-pointer px-5 py-4 text-sm font-extrabold text-slate-800">Dokumen tambahan yang belum tersedia ({{ $optionalIncomplete->count() }})</summary>
                        <div class="border-t border-slate-100 px-5 py-4">
                            <div class="space-y-3">
                                @foreach($optionalIncomplete as $item)
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">{{ $item['label'] }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $item['message'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif

                @if($completed->isNotEmpty())
                    <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <summary class="cursor-pointer px-5 py-4 text-sm font-extrabold text-slate-800">Sudah lengkap ({{ $completed->count() }})</summary>
                        <div class="border-t border-slate-100 px-5 py-4">
                            <div class="space-y-2">
                                @foreach($completed as $item)
                                    <div class="flex items-center gap-2 text-sm text-slate-600"><span class="font-black text-emerald-600">✓</span><span>{{ $item['label'] }}</span></div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
