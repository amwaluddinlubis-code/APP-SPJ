<section class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]">Data Umum Dokumen</h3>
        <span class="text-[11px] font-medium text-[var(--ui-fg-muted)]">Isian umum Paket SPJ</span>
    </div>

    <div class="mt-2 grid gap-3 lg:grid-cols-2 lg:items-start">
        <div class="min-w-0">
            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Uraian pembayaran</label>
            <x-ui.textarea name="payment_description" rows="5" class="mt-1 !min-h-[8.75rem] !py-1.5 !text-sm">{{ old('payment_description', $transaction->payment_description) }}</x-ui.textarea>
        </div>

        <div class="grid min-w-0 gap-2 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Metode pembayaran</label>
                <x-ui.select name="payment_method" class="mt-1 !py-1.5 !text-sm">
                    @foreach(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $transaction->payment_method ?: ($transaction->is_siplah ? 'siplah' : 'tunai')) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Referensi pembayaran</label>
                <x-ui.input name="payment_reference" :value="old('payment_reference', $transaction->payment_reference)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Penerima Utama</label>
                <x-ui.input name="receipt_recipient_name" :value="old('receipt_recipient_name', $transaction->receipt_recipient_name)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Nama penyedia / penerima</label>
                <x-ui.input name="vendor_name" :value="old('vendor_name', $transaction->vendor_name)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Nama pemilik / direktur</label>
                <x-ui.input name="vendor_owner" :value="old('vendor_owner', $transaction->vendor_owner)" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">NPWP penyedia</label>
                <x-ui.input name="vendor_npwp" :value="old('vendor_npwp', $transaction->vendor_npwp)" class="mt-1 !py-1.5 !text-sm" />
            </div>
        </div>
    </div>
</section>
