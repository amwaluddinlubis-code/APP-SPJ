<div class="mt-3 flex flex-wrap items-center gap-3">
    <button @disabled(($transaction->spjPackage && !$transaction->spjPackage->isEditable()) || $transaction->items->isEmpty())
        class="ui-btn ui-btn-primary justify-center px-4 py-2 text-sm">
        {{ $transaction->spjPackage ? ($transaction->spjPackage->isEditable() ? 'Simpan Perbaikan Paket' : 'Paket Terkunci') : 'Buat Paket SPJ' }}
    </button>
    <p class="text-xs text-[var(--ui-fg-muted)]">
        {{ $transaction->spjPackage?->isEditable() ? 'Paket sudah ada tetapi belum dikunci. Koreksi data lalu simpan kembali.' : 'Isi hanya bagian yang sesuai kategori. Bagian lain otomatis disembunyikan.' }}
    </p>
</div>
@if ($transaction->items->isEmpty())
    <p class="mt-2 text-xs text-rose-600">Paket belum dapat dibuat karena rincian transaksi belum tersedia.</p>
@elseif(!$descriptionsComplete)
    <p class="mt-2 text-xs text-amber-700">{{ $descriptionsFilled }} dari
        {{ $transaction->items->count() }} uraian item sudah lengkap. Simpan uraian di tabel bawah sebelum membuat paket.</p>
@endif
</fieldset>
</form>
@if ($packageLocked)
    </div>
    </details>
@endif

</div>
</section>
