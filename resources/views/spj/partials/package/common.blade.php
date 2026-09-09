<section class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-strong)]">Data Umum Dokumen</h3>
        <span class="text-[11px] font-medium text-[var(--ui-fg-muted)]">Field bertanda * wajib diisi sebelum penomoran</span>
    </div>

    @if($transaction->is_siplah)
        @php($siplahResponse = data_get($transaction->siplah_metadata, 'siplahResponse', []))
        <div class="mt-2 grid gap-2 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] px-3 py-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
            <div><span class="block text-[var(--ui-fg-muted)]">Marketplace</span><strong class="text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'marketplace_displayname') ?: 'SiPLah' }}</strong></div>
            <div><span class="block text-[var(--ui-fg-muted)]">ID transaksi</span><strong class="break-all font-mono text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'transaction_id') ?: $transaction->siplah_transaction_id ?: '—' }}</strong></div>
            <div><span class="block text-[var(--ui-fg-muted)]">Alamat penyedia</span><strong class="text-[var(--ui-fg-strong)]">{{ data_get($siplahResponse, 'merchant_address') ?: '—' }}</strong></div>
            <div><span class="block text-[var(--ui-fg-muted)]">Tanggal pembayaran</span><strong class="text-[var(--ui-fg-strong)]">{{ $transaction->siplah_payment_date?->translatedFormat('d F Y H:i') ?: '—' }}</strong></div>
        </div>
    @endif

    <div class="mt-2 grid gap-3 lg:grid-cols-2 lg:items-start">
        <div class="min-w-0">
            <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Uraian pembayaran <span class="text-rose-600">*</span></label>
            @php($siplahMarketplace = data_get($transaction->siplah_metadata, 'siplahResponse.marketplace_displayname'))
            @php($siplahInvoice = data_get($transaction->siplah_metadata, 'siplahResponse.invoice_number'))
            @php($paymentDescriptionDefault = $siplahMarketplace ? 'Pembelian barang melalui '.$siplahMarketplace.($siplahInvoice ? ' berdasarkan invoice '.$siplahInvoice : '') : 'Pembelian barang melalui SiPLah')
            <x-ui.textarea name="payment_description" rows="5" class="mt-1 !min-h-[8.75rem] !py-1.5 !text-sm" required>{{ old('payment_description', $transaction->payment_description ?: ($transaction->is_siplah ? $paymentDescriptionDefault : null)) }}</x-ui.textarea>
        </div>

        <div class="grid min-w-0 gap-2 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Metode pembayaran <span class="text-rose-600">*</span></label>
                <x-ui.select name="payment_method" class="mt-1 !py-1.5 !text-sm" required>
                    @foreach(['tunai' => 'Tunai', 'transfer_bank' => 'Transfer Bank', 'siplah' => 'SiPLah'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $transaction->payment_method ?: ($transaction->is_siplah ? 'siplah' : 'tunai')) === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Referensi pembayaran</label>
                @php($siplahOrder = $transaction->siplah_order_number ?: (filled($siplahInvoice) ? collect(explode('/', $siplahInvoice))->filter()->last() : null))
                <x-ui.input name="payment_reference" :value="old('payment_reference', $transaction->payment_reference ?: ($transaction->is_siplah ? $siplahOrder : null))" class="mt-1 !py-1.5 !text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-[var(--ui-fg-strong)]">Penerima Utama <span class="text-rose-600">*</span></label>
                <x-ui.input name="receipt_recipient_name" :value="old('receipt_recipient_name', $transaction->receipt_recipient_name ?: ($transaction->is_siplah ? data_get($siplahResponse, 'merchant') : null))" class="mt-1 !py-1.5 !text-sm" required />
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
