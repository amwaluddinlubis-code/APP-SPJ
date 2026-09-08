<section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Status Paket SPJ</p>
            @if ($transaction->spjPackage)
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-ui.status-badge :status="$transaction->spjPackage->status" />
                    @if ($transaction->spj_category)
                        <x-ui.badge variant="theme">{{ $spjTypeLabel($transaction->spj_category) }}</x-ui.badge>
                    @endif
                    @if ($transaction->spjPackage->document_number)
                        <span class="font-mono text-sm font-bold text-[var(--ui-fg-strong)]">{{ $transaction->spjPackage->document_number }}</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-[var(--ui-fg-muted)]">
                    Seluruh isian dokumen, kategori, penerima, vendor, perjalanan, honor, dan penomoran dikelola di halaman Paket SPJ.
                </p>
            @else
                <p class="mt-2 font-semibold text-[var(--ui-fg-strong)]">Belum ada paket SPJ.</p>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">
                    Detail transaksi hanya menyimpan data sumber dan uraian item. Buat draft untuk mulai melengkapi dokumen SPJ.
                </p>
            @endif
        </div>

        <div class="flex flex-col items-start gap-2 lg:items-end">
            <div class="text-xs text-[var(--ui-fg-muted)]">
                Uraian item SPJ: <strong class="text-[var(--ui-fg-strong)]">{{ $descriptionsFilled }}/{{ $transaction->items->count() }}</strong> lengkap
            </div>

            @if (! $descriptionsComplete)
                <p class="max-w-sm text-xs text-amber-700 lg:text-right">
                    Simpan seluruh Uraian Barang/Jasa untuk SPJ terlebih dahulu sebelum Paket SPJ dapat dibuka.
                </p>
                <a href="#rincian-transaksi" class="ui-btn ui-btn-secondary px-4 py-2 text-sm">
                    Lengkapi Uraian Item
                </a>
            @else
                <div x-show="itemDescriptionsDirty" x-cloak class="flex flex-col items-start gap-2 lg:items-end">
                    <p class="max-w-sm text-xs text-amber-700 lg:text-right">
                        Ada perubahan uraian item yang belum tersimpan. Simpan perubahan sebelum melihat Paket SPJ.
                    </p>
                    <a href="#rincian-transaksi" class="ui-btn ui-btn-secondary px-4 py-2 text-sm">
                        Simpan Perubahan Uraian
                    </a>
                </div>

                <div x-show="!itemDescriptionsDirty" class="flex flex-col items-start gap-2 lg:items-end">
                    @if ($transaction->spjPackage)
                        <a href="{{ route('transactions.prepare-spj', $transaction->id) }}"
                            class="ui-btn ui-btn-primary px-4 py-2 text-sm">
                            Lihat Paket SPJ
                        </a>
                    @elseif($transaction->items->isNotEmpty())
                        <a href="{{ route('transactions.prepare-spj', $transaction->id) }}"
                            class="ui-btn ui-btn-primary px-4 py-2 text-sm">
                            Siapkan Paket SPJ
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
