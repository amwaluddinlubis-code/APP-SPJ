<x-layouts.tailwind-app>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-6 text-white sm:px-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-bold uppercase tracking-[.18em] text-sky-200">Priority #10 · Checklist paket SPJ</p>
                        <h1 class="mt-2 text-2xl font-bold tracking-tight">Apa yang masih kurang sebelum READY?</h1>
                        <p class="mt-2 text-sm leading-6 text-indigo-100">Periksa satu per satu. Item hijau sudah aman, item kuning masih harus dilengkapi operator.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white">Buka paket lengkap</a>
                        <a href="{{ route('transactions.show', $package->transaction->id) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-bold text-indigo-950">Edit transaksi</a>
                    </div>
                </div>
            </div>

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Status paket</p>
                    <div class="mt-2"><x-ui.status-badge :status="$package->status" size="xs" /></div>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Checklist selesai</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ $completedChecks }} / {{ $totalChecks }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Masih kurang</p>
                    <p class="mt-1 text-2xl font-bold {{ $remainingChecks > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $remainingChecks }}</p>
                </div>
                <div class="bg-white px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Kesiapan</p>
                        <span class="text-sm font-bold text-indigo-700">{{ $progress }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.35fr_.65fr]">
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="font-bold text-slate-900">Checklist kelengkapan</h2>
                    <p class="mt-1 text-sm text-slate-500">Aturan checklist ini sama dengan validasi yang digunakan saat paket ditandai READY.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($checklist as $check)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                @if($check['passed'])
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-sm font-black text-emerald-700">✓</span>
                                @else
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-amber-100 text-sm font-black text-amber-700">!</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $check['group'] }}</p>
                                    <h3 class="mt-0.5 font-bold text-slate-900">{{ $check['label'] }}</h3>
                                    <p class="mt-1 text-sm leading-6 {{ $check['passed'] ? 'text-slate-500' : 'text-amber-800' }}">{{ $check['message'] }}</p>
                                </div>
                            </div>
                            @if(!$check['passed'])
                                <a href="{{ $check['url'] }}" class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100">Perbaiki sekarang →</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>

            <aside class="space-y-4">
                <section class="rounded-2xl border {{ $remainingChecks > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-5 shadow-sm">
                    @if($remainingChecks > 0)
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-600">Belum dapat READY</p>
                        <h2 class="mt-2 text-lg font-bold text-amber-950">Selesaikan {{ $remainingChecks }} item lagi</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-800">Gunakan tombol “Perbaiki sekarang” pada checklist. Setelah data disimpan, kembali ke halaman ini untuk memeriksa ulang.</p>
                    @else
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-600">Checklist lengkap</p>
                        <h2 class="mt-2 text-lg font-bold text-emerald-950">Paket siap menjadi READY</h2>
                        <p class="mt-2 text-sm leading-6 text-emerald-800">Semua pemeriksaan wajib sudah lolos. Paket dapat dilanjutkan ke tahap penomoran.</p>

                        @if($canMarkReady)
                            <form method="POST" action="{{ route('spj.ready', $package->id) }}" class="mt-4">
                                @csrf
                                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-800">Tandai READY →</button>
                            </form>
                        @elseif($package->status !== 'DRAFT')
                            <div class="mt-4"><x-ui.status-badge :status="$package->status" /></div>
                        @elseif(!$canEdit)
                            <p class="mt-4 rounded-lg border border-slate-200 bg-white/70 p-3 text-sm font-semibold text-slate-600">Mode pemeriksa: checklist dapat dilihat, tetapi status paket tidak dapat diubah.</p>
                        @endif
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Paket yang diperiksa</p>
                    <p class="mt-2 font-mono text-sm font-bold text-indigo-700">{{ $package->transaction->no_bukti ?: 'Tanpa nomor bukti' }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-800">{{ $package->transaction->payment_description ?: $package->transaction->description ?: 'Uraian belum tersedia' }}</p>
                    <p class="mt-2 text-xs text-slate-500">Kategori: {{ str_replace('_', ' ', (string) $package->transaction->spj_category) }}</p>
                </section>
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
