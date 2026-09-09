@php
    $primaryNameKey = 'name';
    $receiptRecipientName = trim((string) ($transaction->receipt_recipient_name ?? ''));
    $initialPrimaryIndex = collect($rows)->search(function ($row) use ($receiptRecipientName): bool {
        return $receiptRecipientName !== ''
            && is_array($row)
            && mb_strtolower(trim((string) ($row['name'] ?? ''))) === mb_strtolower($receiptRecipientName);
    });
    if ($initialPrimaryIndex === false) {
        $initialPrimaryIndex = count($rows) > 0 ? 0 : null;
    }
@endphp

<div
    x-data="{
        rows: @js(array_values($rows)),
        emptyRow: @js($emptyRow),
        primaryIndex: @js($initialPrimaryIndex),
        addRow() {
            this.rows.push({...this.emptyRow});
            if (this.primaryIndex === null) this.primaryIndex = 0;
        },
        removeRow(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) {
                this.primaryIndex = null;
            } else if (this.primaryIndex === index) {
                this.primaryIndex = Math.min(index, this.rows.length - 1);
            } else if (this.primaryIndex > index) {
                this.primaryIndex -= 1;
            }
        }
    }"
    class="mt-3"
>
    <input type="hidden" name="primary_recipient_group" value="service_recipients">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-xs" style="color: var(--ui-fg-muted)">Isi penerima jasa dan nilai sampai totalnya sesuai dengan bruto transaksi.</p>
        <x-ui.button type="button" variant="secondary" x-on:click="addRow()" class="shrink-0 !min-h-8 !px-2.5 !py-1 text-xs">
            <span aria-hidden="true">＋</span> Tambah Penerima
        </x-ui.button>
    </div>

    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="index">
            <article class="rounded-lg border p-3" style="border-color: var(--ui-line); background: var(--ui-surface-base)">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold" style="background: var(--theme-accent-soft); color: var(--theme-content-accent)" x-text="index + 1"></span>
                        <span class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Penerima jasa</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 text-xs" style="color: var(--ui-fg-muted)">
                            <input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" class="h-4 w-4" title="Jadikan penerima utama">
                            Utama
                        </label>
                        <button type="button" x-on:click="removeRow(index)" title="Hapus penerima" class="inline-flex h-7 w-7 items-center justify-center rounded border text-sm font-bold text-rose-700 transition hover:bg-rose-50" style="border-color: var(--ui-line)">×</button>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="sm:col-span-2 lg:col-span-2">
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Nama Penerima <span class="text-rose-600">*</span></span>
                        <input type="text" :name="'service_recipients[' + index + '][name]'" x-model="row.name" required class="ui-input mt-1 !min-h-8 !py-1.5 text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Jenis Jasa</span>
                        <input type="text" :name="'service_recipients[' + index + '][service_type]'" x-model="row.service_type" class="ui-input mt-1 !min-h-8 !py-1.5 text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Volume</span>
                        <input type="number" min="0" step="0.01" :name="'service_recipients[' + index + '][quantity]'" x-model.number="row.quantity" class="ui-input mt-1 !min-h-8 !py-1.5 text-right text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Hari / Kali</span>
                        <input type="number" min="0" step="1" :name="'service_recipients[' + index + '][rental_days]'" x-model.number="row.rental_days" class="ui-input mt-1 !min-h-8 !py-1.5 text-right text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Tarif</span>
                        <input type="number" min="0" step="0.01" :name="'service_recipients[' + index + '][daily_rate]'" x-model.number="row.daily_rate" class="ui-input mt-1 !min-h-8 !py-1.5 text-right text-xs">
                    </label>
                </div>

                <details class="mt-3 rounded-md border px-3 py-2" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                    <summary class="cursor-pointer text-xs font-semibold" style="color: var(--ui-fg-muted)">Detail tambahan</summary>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach([
                            'npwp' => ['NPWP', 'text'],
                            'unit' => ['Satuan', 'text'],
                            'usage_started_at' => ['Mulai', 'date'],
                            'usage_completed_at' => ['Selesai', 'date'],
                            'receipt_number' => ['Ref. Kuitansi', 'text'],
                            'payment_reference' => ['Ref. Pembayaran', 'text'],
                            'agreement_number' => ['No. Perjanjian', 'text'],
                            'agreement_date' => ['Tgl Perjanjian', 'date'],
                        ] as $key => [$label, $type])
                            <label>
                                <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</span>
                                <input type="{{ $type }}" :name="'service_recipients[' + index + '][{{ $key }}]'" x-model="row.{{ $key }}" class="ui-input mt-1 !min-h-8 !py-1.5 text-xs">
                            </label>
                        @endforeach
                        <label class="sm:col-span-2 lg:col-span-4">
                            <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Uraian Jasa</span>
                            <textarea rows="2" :name="'service_recipients[' + index + '][service_description]'" x-model="row.service_description" class="ui-textarea mt-1 !min-h-16 !py-1.5 text-xs"></textarea>
                        </label>
                        <label class="sm:col-span-2 lg:col-span-4">
                            <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Catatan</span>
                            <textarea rows="2" :name="'service_recipients[' + index + '][notes]'" x-model="row.notes" class="ui-textarea mt-1 !min-h-16 !py-1.5 text-xs"></textarea>
                        </label>
                    </div>
                </details>
            </article>
        </template>

        <div x-show="rows.length === 0" class="rounded-lg border border-dashed px-3 py-6 text-center text-xs" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">
            Belum ada penerima jasa. Klik “Tambah Penerima”.
        </div>
    </div>
</div>
