<section id="modul-buat-spj"
    class="spj-builder order-2 overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm"
    x-data="{ category: '{{ $selectedSpjType }}', paymentMethod: @js($paymentMethod), isSiplah: @js($isSiplah) }">
    <div class="spj-builder-header border-b px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="inline-flex rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-bold text-[var(--text-comfort-on-dark)]">
                    MODUL PEMBUATAN SPJ</p>
                <h2 class="mt-1 font-mono text-lg font-bold uppercase text-[var(--text-comfort-on-dark)]">
                    Siapkan dokumen berdasarkan kategori SPJ</h2>
                <p class="mt-1 text-sm text-[var(--text-comfort-on-dark-soft)]">Pilih skenario dokumen,
                    pastikan uraian setiap item lengkap, lalu buat paket SPJ.</p>
            </div>
            @if ($transaction->spjPackage)
                <a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) }}"
                    class="spj-builder-primary inline-flex w-fit rounded-lg px-4 py-2 text-sm font-bold shadow-sm">Buka
                    Paket SPJ →</a>
            @endif
        </div>
    </div>

    <div class="p-4 sm:p-5">
        @if ($packageLocked)
            <div class="mb-3 grid gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Kategori</p>
                    <p class="mt-1 font-semibold text-[var(--ui-fg-strong)]">{{ $spjTypeLabel($selectedSpjType) }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Cara bayar</p>
                    <p class="mt-1 font-semibold text-[var(--ui-fg-strong)]">{{ ['transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah', 'tunai' => 'Tunai'][$paymentMethod] ?? 'Belum diisi' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Penerima kuitansi</p>
                    <p class="mt-1 font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->effective_receipt_recipient_name ?: 'Belum diisi' }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Perlu dilengkapi</p>
                    <p class="mt-1 font-semibold {{ $pendingChecklist->isEmpty() ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $pendingChecklist->isEmpty() ? 'Tidak ada' : $pendingChecklist->count() . ' data' }}</p>
                </div>
            </div>
            <details class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-[var(--ui-fg-strong)]">Lihat rincian data paket terkunci</summary>
                <div class="border-t border-[var(--ui-line)] p-3">
        @endif
        <form method="POST" action="{{ route('spj.prepare', $transaction->id) }}"
            class="spj-builder-form rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3 sm:p-4">
            @csrf
            <input type="hidden" name="ppn_rate" value="{{ $effectiveTaxRate($transaction->ppn_rate, $transaction->ppn) }}">
            <input type="hidden" name="pph21_rate" value="{{ $effectiveTaxRate($transaction->pph21_rate, $transaction->pph21) }}">
            <input type="hidden" name="pph22_rate" value="{{ $effectiveTaxRate($transaction->pph22_rate, $transaction->pph22) }}">
            <input type="hidden" name="pph23_rate" value="{{ $effectiveTaxRate($transaction->pph23_rate, $transaction->pph23) }}">
            <input type="hidden" name="pph4_rate" value="{{ $effectiveTaxRate($transaction->pph4_rate, $transaction->pph4) }}">
            <input type="hidden" name="sspd_rate" value="{{ $effectiveTaxRate($transaction->sspd_rate, $transaction->sspd) }}">
            @if ($transaction->spjPackage && !$transaction->spjPackage->isEditable())
                <div class="mb-3 flex items-start gap-2 rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-3 py-2 text-sm text-[var(--ui-fg-strong)]">
                    <span aria-hidden="true">🔒</span>
                    <p><strong>Paket terkunci.</strong> Batalkan penomoran lalu buka paket untuk koreksi sebelum mengubah isian.</p>
                </div>
            @endif
            <fieldset @disabled($transaction->spjPackage && !$transaction->spjPackage->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
                <div class="mb-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                    <span class="font-black">*</span>
                    <p><strong>Wajib diisi.</strong> Penanda menyesuaikan kategori SPJ yang dipilih; field tanpa tanda bintang bersifat opsional atau terisi otomatis.</p>
                </div>
                <div class="spj-builder-accent-panel grid gap-3 rounded-lg border border-[var(--ui-line)] p-3 lg:grid-cols-4">
                    <div>
                        <label for="detail-spj-type" class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]">Kategori SPJ <span class="text-rose-600">* Wajib diisi</span></label>
                        <select id="detail-spj-type" name="spj_category" x-model="category" class="ui-select mt-1">
                            <option value="">Pilih kategori SPJ</option>
                            @foreach (array_keys($spjGuidance) as $type)
                                <option value="{{ $type }}">{{ $spjGuidance[$type]['title'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="category === 'BARANG'" :disabled="category !== 'BARANG'" x-cloak
                        class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)]/80 p-2.5 col-span-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]">Jenis Belanja Barang</p>
                        <input type="hidden" name="is_siplah" x-model="isSiplah">
                        <div class="mt-2 flex flex-wrap gap-3 text-sm text-[var(--ui-fg)]">
                            <label class="inline-flex items-center gap-2"><input type="radio"
                                    :checked="isSiplah" @change="isSiplah = true; paymentMethod = 'siplah'"> Belanja SiPLah</label>
                            <label class="inline-flex items-center gap-2"><input type="radio"
                                    :checked="!isSiplah" @change="isSiplah = false; if (paymentMethod === 'siplah') paymentMethod = 'tunai'"> Belanja offline</label>
                        </div>
                        <p class="mt-2 text-xs text-[var(--ui-fg-muted)]"
                            x-text="isSiplah ? 'Gunakan data marketplace SiPLah.' : 'Gunakan data pesanan, BAP, dan BAST internal.'"></p>
                    </div>
                </div>

                <div class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Isian umum semua kategori</p>
                    <div class="mt-2 grid gap-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Uraian dari ARKAS
                                <span class="font-normal text-[var(--ui-fg-muted)]">(referensi readonly)</span></label>
                            <textarea name="description" rows="2" readonly class="ui-textarea ui-input-readonly mt-1 text-sm">{{ $transaction->description }}</textarea>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Uraian dokumen / pembayaran <span class="text-rose-600">*</span></label>
                            <textarea name="payment_description" rows="5" required class="ui-textarea mt-1 text-sm"
                                placeholder="Uraian yang akan dipakai pada dokumen SPJ">{{ $transaction->payment_description }}</textarea>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Metode Pembayaran <span class="text-rose-600">*</span></label>
                            <select name="payment_method" x-model="paymentMethod" required class="ui-select mt-1 text-sm">
                                <option value="transfer_bank" @selected($paymentMethod === 'transfer_bank')>Transfer Bank (CMS / Non Tunai)</option>
                                <option value="siplah" @selected($paymentMethod === 'siplah')>SiPLah Kemdikbud</option>
                                <option value="tunai" @selected($paymentMethod === 'tunai')>Tunai Kas BOS</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Referensi Pembayaran <span x-show="paymentMethod === 'siplah'" class="text-rose-600">*</span></label>
                            <input name="payment_reference" x-bind:required="paymentMethod === 'siplah'"
                                value="{{ $transaction->payment_reference }}" class="ui-input mt-1 text-sm"
                                placeholder="No. cek/CMS/kuitansi">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Penerima Kuitansi <span class="text-rose-600">*</span></label>
                            <input name="receipt_recipient_name" required
                                value="{{ $transaction->receipt_recipient_name ?: $transaction->effective_receipt_recipient_name }}"
                                class="ui-input mt-1 text-sm" placeholder="Boleh berbeda dari penerima BKU">
                        </div>
                    </div>
                </div>
