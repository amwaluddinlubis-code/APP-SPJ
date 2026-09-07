<fieldset data-spj-section="BARANG KONSUMSI" @disabled(!in_array($selectedSpjType, ['BARANG', 'KONSUMSI'], true)) @if(!in_array($selectedSpjType, ['BARANG', 'KONSUMSI'], true)) hidden @endif class="min-w-0 space-y-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
    <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Dokumen Pengadaan</h3>
    <fieldset data-spj-procurement="internal" class="min-w-0 grid gap-3 sm:grid-cols-2">
        @foreach(['order' => 'Pesanan', 'bap' => 'BAP', 'bast' => 'BAST'] as $key => $label)
            <x-ui.field :label="'Nomor '.$label" hint="Diterbitkan melalui penomoran.">
                <x-ui.input readonly :name="$key.'_number'" :value="$purchaseDetails?->{$key.'_number'} ?: $transaction->{$key.'_number'}" placeholder="Belum bernomor" />
            </x-ui.field>
            <x-ui.field :label="'Tanggal '.$label">
                <x-ui.input type="date" :name="$key.'_date'" :value="old($key.'_date', ['order' => $orderDate, 'bap' => $bapDate, 'bast' => $bastDate][$key])" :max="$key === 'order' ? $transactionDateLimit : null" />
            </x-ui.field>
        @endforeach
    </fieldset>
    <fieldset data-spj-procurement="siplah" class="min-w-0 grid gap-3 sm:grid-cols-2">
        <p class="text-xs text-[var(--ui-fg-muted)] sm:col-span-2">Referensi SiPLah memakai data marketplace; nomor Surat Pesanan internal tidak diperlukan.</p>
        <x-ui.field label="Nomor pesanan SiPLah"><x-ui.input name="siplah_order_number" :value="old('siplah_order_number', $transaction->siplah_order_number)" /></x-ui.field>
    </fieldset>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.field label="Nomor invoice"><x-ui.input name="invoice_number" :value="old('invoice_number', $transaction->invoice_number)" /></x-ui.field>
        <x-ui.field label="Tanggal invoice"><x-ui.input type="date" name="invoice_date" :value="old('invoice_date', $transaction->invoice_date?->format('Y-m-d'))" :max="$transactionDateLimit" /></x-ui.field>
        <x-ui.field label="Status invoice"><x-ui.input name="invoice_status" :value="old('invoice_status', $transaction->invoice_status)" /></x-ui.field>
    </div>
</fieldset>
