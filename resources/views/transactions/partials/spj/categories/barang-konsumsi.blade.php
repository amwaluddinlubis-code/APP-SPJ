<fieldset x-show="paymentMethod === 'siplah'" :disabled="paymentMethod !== 'siplah'" x-cloak
    class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">
        Data Pembelian SiPLah</p>
    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Nomor Pesanan SiPLah adalah nomor order
        marketplace dan berbeda dari Nomor Surat Pesanan SPJ yang diterbitkan aplikasi.</p>
    <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Penyedia
                SiPLah</label><input name="vendor_name"
                value="{{ $transaction->vendor_name }}" class="ui-input mt-1 text-sm"
                placeholder="Nama penyedia"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Pemilik/Penanggung
                Jawab</label><input name="vendor_owner"
                value="{{ $transaction->vendor_owner }}" class="ui-input mt-1 text-sm"
                placeholder="Opsional"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">NPWP
                Penyedia</label><input name="vendor_npwp"
                value="{{ $transaction->vendor_npwp }}" class="ui-input mt-1 text-sm"
                placeholder="NPWP penyedia"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Nomor Pesanan
                SiPLah</label><input name="siplah_order_number"
                value="{{ $transaction->siplah_order_number }}" class="ui-input mt-1 text-sm"
                placeholder="Nomor order marketplace" required></div>
    </div>
</fieldset>

<div x-show="['BARANG','KONSUMSI'].includes(category)" x-cloak
    x-data="{ orderDate: @js($purchaseOrderDate), bapDate: @js($purchaseBapDate), bastDate: @js($purchaseBastDate), invoiceDate: @js($purchaseInvoiceDate) }"
    class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">
        Data pembelian barang/konsumsi</p>
    <div class="mt-2 grid gap-2 md:grid-cols-3 xl:grid-cols-3">
        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                    Pesanan</label><input type="date" name="order_date"
                    x-model="orderDate" :max="bapDate || @js($transactionDateLimit)"
                    class="ui-input mt-1 text-sm"></div>
        </template>
        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                    BAP</label><input type="date" name="bap_date" x-model="bapDate"
                    :min="orderDate || null" :max="bastDate || @js($transactionDateLimit)"
                    class="ui-input mt-1 text-sm"></div>
        </template>
        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                    BAST</label><input type="date" name="bast_date" x-model="bastDate"
                    :min="bapDate || null" :max="invoiceDate || @js($transactionDateLimit)"
                    class="ui-input mt-1 text-sm"></div>
        </template>

        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl Invoice
                <span x-show="paymentMethod === 'siplah'" class="text-rose-600">*</span></label><input
                type="date" name="invoice_date" x-model="invoiceDate"
                x-bind:required="paymentMethod === 'siplah'"
                :min="paymentMethod === 'siplah' ? null : (bastDate || null)"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm"></div>

        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No.
                Invoice/Faktur <span x-show="paymentMethod === 'siplah'"
                    class="text-rose-600">*</span></label><input name="invoice_number"
                x-bind:required="paymentMethod === 'siplah'"
                value="{{ $transaction->invoice_number }}" class="ui-input mt-1 text-sm"
                placeholder="No. invoice"></div>

        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Status
                Invoice</label><input name="invoice_status"
                value="{{ $transaction->invoice_status }}" class="ui-input mt-1 text-sm"
                placeholder="Contoh: Lunas"></div>
        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No.
                    Pesanan <span class="font-normal text-emerald-600 dark:text-emerald-400">(otomatis)</span></label><input
                    readonly name="order_number"
                    value="{{ $purchaseDetails?->order_number ?: $transaction->order_number }}"
                    class="ui-input ui-input-readonly mt-1 text-sm"></div>
        </template>

        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No. BAP
                    <span class="font-normal text-emerald-600 dark:text-emerald-400">(otomatis)</span></label><input
                    readonly name="bap_number"
                    value="{{ $purchaseDetails?->bap_number ?: $transaction->bap_number }}"
                    class="ui-input ui-input-readonly mt-1 text-sm"
                    placeholder="Terbit setelah penomoran"></div>
        </template>

        <template x-if="paymentMethod !== 'siplah'">
            <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No. BAST
                    <span class="font-normal text-emerald-600 dark:text-emerald-400">(otomatis)</span></label><input
                    readonly name="bast_number"
                    value="{{ $purchaseDetails?->bast_number ?: $transaction->bast_number }}"
                    class="ui-input ui-input-readonly mt-1 text-sm"
                    placeholder="Terbit setelah penomoran"></div>
        </template>
    </div>
</div>
