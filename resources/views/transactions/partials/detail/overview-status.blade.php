<section class="grid gap-4 lg:grid-cols-[minmax(0,.75fr)_minmax(0,1.25fr)]">
    <details class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Alur operator</p>
                <h2 class="mt-1 font-bold text-[var(--ui-fg-strong)]">Lihat status pekerjaan</h2>
            </div>
            <x-ui.badge :variant="$readyCount === count($readinessChecklist) ? 'success' : 'warning'">{{ $readyCount }}/{{ count($readinessChecklist) }}
                siap</x-ui.badge>
        </summary>
        <div class="border-t border-[var(--ui-line)] p-4">
            <ol class="mt-4 space-y-2 text-sm">
                <li class="flex gap-2"><span
                        class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">1</span><span><strong
                            class="text-[var(--ui-fg-strong)]">Pilih konteks</strong><br><span
                            class="text-xs text-[var(--ui-fg-muted)]">Sekolah, tahun, dan sumber dana aktif.</span></span></li>
                <li class="flex gap-2"><span
                        class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">2</span><span><strong
                            class="text-[var(--ui-fg-strong)]">Cek data ARKAS</strong><br><span
                            class="text-xs text-[var(--ui-fg-muted)]">{{ $sourceStatus === 'SOURCE_MISSING' ? 'Tidak muncul di sync terakhir.' : 'Data sumber aktif.' }}</span></span></li>
                <li class="flex gap-2"><span
                        class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ filled($selectedSpjType) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]' }} text-xs font-bold">3</span><span><strong
                            class="text-[var(--ui-fg-strong)]">Lengkapi data manual</strong><br><span
                            class="text-xs text-[var(--ui-fg-muted)]">Kategori, uraian, penerima kuitansi, dan detail sesuai SPJ.</span></span></li>
                <li class="flex gap-2"><span
                        class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ $transaction->spjPackage ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]' }} text-xs font-bold">4</span><span><strong
                            class="text-[var(--ui-fg-strong)]">Buat paket SPJ</strong><br><span
                            class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->spjPackage ? 'Paket sudah dibuat.' : 'Belum dibuat.' }}</span></span></li>
                <li class="flex gap-2"><span
                        class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full {{ $transaction->spjPackage?->document_number ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]' }} text-xs font-bold">5</span><span><strong
                            class="text-[var(--ui-fg-strong)]">Nomor & arsip</strong><br><span
                            class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->spjPackage?->document_number ?: 'Belum bernomor.' }}</span></span></li>
            </ol>
        </div>
    </details>

    <x-ui.panel variant="default" :padding="false" class="col-span-2">
        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Checklist kelengkapan</p>
                    <h2 class="mt-1 font-bold text-[var(--ui-fg-strong)]">Yang perlu dilengkapi</h2>
                </div>
                <a href="#modul-buat-spj" class="ui-btn ui-btn-primary !min-h-0 !py-1.5 !px-3 !text-xs">Isi data</a>
            </div>
            @if ($pendingChecklist->isEmpty())
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
                    <p class="font-bold">Semua data yang diperiksa sudah lengkap.</p>
                    <p class="mt-1 text-xs">Lanjutkan ke paket SPJ atau buka daftar data lengkap di bawah.</p>
                </div>
            @else
                <div class="mt-4 grid gap-2 md:grid-cols-2">
                    @foreach ($pendingChecklist as $item)
                        <div class="rounded-lg border {{ $item['ready'] ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/40' : 'border-[var(--ui-line)] bg-[var(--ui-surface-soft)]' }} p-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full {{ $item['ready'] ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }} text-[11px] font-bold">{{ $item['ready'] ? '✓' : '!' }}</span>
                                <p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $item['label'] }}</p>
                                <span class="text-xs text-[var(--ui-fg-muted)]">{{ $item['ready'] ? 'Sudah tersedia.' : $item['hint'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($completedChecklist->isNotEmpty())
                <details class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-2">
                    <summary class="cursor-pointer text-xs font-bold text-[var(--ui-fg-strong)]">Lihat {{ $completedChecklist->count() }} data yang sudah lengkap</summary>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($completedChecklist as $item)
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-300">✓ {{ $item['label'] }}</span>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </x-ui.panel>
</section>

<section class="grid gap-4 lg:grid-cols-4 xl:grid-cols-4">
    <article class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-[var(--ui-fg-strong)]">Rincian Pajak</h2><span
                class="font-bold text-[var(--ui-fg-strong)]">{{ $rupiah($transaction->tax_total) }}</span>
        </div>
        <div class="mt-4 grid gap-x-6 gap-y-3 text-base sm:grid-cols-2">
            @forelse($taxBreakdown as $label => $value)
                <div class="flex justify-between gap-3 border-b border-[var(--ui-line)] pb-2"><span
                        class="text-[var(--ui-fg-muted)]">{{ $label }}</span><span
                        class="font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($value) }}</span></div>
            @empty
                <div class="text-sm text-[var(--ui-fg-muted)]">Tidak ada potongan pajak pada transaksi ini.</div>
            @endforelse
        </div>
    </article>
    <article class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow">
        <h2 class="font-bold text-[var(--ui-fg-strong)]">Informasi Dokumen SPJ</h2>
        <dl class="mt-4 space-y-3 text-base">
            <div class="flex justify-between gap-4">
                <dt class="text-[var(--ui-fg-muted)]">Nomor SPJ</dt>
                <dd class="text-right font-semibold {{ $transaction->spjPackage?->status === 'CANCELLED' ? 'text-rose-700 line-through dark:text-rose-300' : 'text-[var(--ui-fg-strong)]' }}">{{ $transaction->spjPackage?->document_number ?: 'Belum ditetapkan' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-[var(--ui-fg-muted)]">Status paket</dt>
                <dd class="font-semibold {{ $transaction->spjPackage?->status === 'CANCELLED' ? 'text-rose-700 dark:text-rose-300' : 'text-[var(--ui-fg-strong)]' }}">{{ $transaction->spjPackage?->status === 'CANCELLED' ? 'Nomor dibatalkan' : ($transaction->spjPackage?->status ?: 'Belum dibuat') }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-[var(--ui-fg-muted)]">Referensi pembayaran</dt>
                <dd class="text-right font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->payment_reference ?: 'Belum ada referensi' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-[var(--ui-fg-muted)]">Pembelian SIPLah</dt>
                <dd class="font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->is_siplah ? 'Ya' : 'Tidak' }}</dd>
            </div>
        </dl>
    </article>
</section>
