<section class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Data Umum Dokumen</h3>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <x-ui.field label="Uraian pembayaran" class="sm:col-span-2">
            <x-ui.textarea name="payment_description" rows="3">{{ old('payment_description', $transaction->payment_description) }}</x-ui.textarea>
        </x-ui.field>
        <x-ui.field label="Metode pembayaran">
            <x-ui.select name="payment_method">
                @foreach(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_method', $transaction->payment_method ?: ($transaction->is_siplah ? 'siplah' : 'tunai')) === $value)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        @foreach(['payment_reference' => 'Referensi pembayaran', 'receipt_recipient_name' => 'Penerima kuitansi', 'vendor_name' => 'Nama penyedia / penerima', 'vendor_owner' => 'Nama pemilik / direktur', 'vendor_npwp' => 'NPWP penyedia'] as $name => $label)
            <x-ui.field :label="$label"><x-ui.input :name="$name" :value="old($name, $transaction->{$name})" /></x-ui.field>
        @endforeach
    </div>
</section>
