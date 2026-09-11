<fieldset
    data-spj-section="BARANG KONSUMSI"
    data-auto-number-pesanan="{{ $purchaseDetails?->order_number ?: $transaction->order_number }}"
    data-auto-number-bap="{{ $purchaseDetails?->bap_number ?: $transaction->bap_number }}"
    data-auto-number-bast="{{ $purchaseDetails?->bast_number ?: $transaction->bast_number }}"
    @disabled(!in_array($selectedSpjType, ['BARANG', 'KONSUMSI'], true))
    @if(!in_array($selectedSpjType, ['BARANG', 'KONSUMSI'], true)) hidden @endif
    class="min-w-0 space-y-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"
>
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Dokumen Pengadaan</h3>

    <fieldset data-spj-procurement="internal" class="min-w-0 grid gap-2 sm:grid-cols-4">
        @foreach(['order' => 'Pesanan', 'bap' => 'BAP', 'bast' => 'BAST'] as $key => $label)
            <x-ui.field :label="'Tanggal '.$label">
                <x-ui.input type="date" :name="$key.'_date'" :value="old($key.'_date', ['order' => $orderDate, 'bap' => $bapDate, 'bast' => $bastDate][$key])" :max="$key === 'order' ? $transactionDateLimit : null" class="!py-1.5 !text-sm" />
            </x-ui.field>
        @endforeach
        <x-ui.field label="Status invoice"><x-ui.input name="invoice_status" :value="old('invoice_status', $transaction->invoice_status)" class="!py-1.5 !text-sm" /></x-ui.field>
    </fieldset>

    <fieldset data-spj-procurement="siplah" class="min-w-0 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="Nomor invoice"><x-ui.input name="invoice_number" :value="old('invoice_number', $transaction->invoice_number)" class="!py-1.5 !text-sm" /></x-ui.field>
        <x-ui.field label="Tanggal invoice"><x-ui.input type="date" name="invoice_date" :value="old('invoice_date', $transaction->invoice_date?->format('Y-m-d'))" :max="$transactionDateLimit" class="!py-1.5 !text-sm" /></x-ui.field>
        <x-ui.field label="No pesanan">
            @php($siplahInvoice = data_get($transaction->siplah_metadata, 'siplahResponse.invoice_number'))
            @php($siplahOrder = $transaction->siplah_order_number ?: (filled($siplahInvoice) ? collect(explode('/', $siplahInvoice))->filter()->last() : null))
            <x-ui.input name="siplah_order_number" :value="old('siplah_order_number', $siplahOrder)" class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Status invoice"><x-ui.input name="invoice_status" :value="old('invoice_status', $transaction->invoice_status)" class="!py-1.5 !text-sm" /></x-ui.field>
    </fieldset>
</fieldset>
