@php($serviceRecipientRows = old('service_recipients', $transaction->serviceRecipients->map(fn($recipient) => $recipient->only(['name', 'service_type', 'quantity', 'unit', 'rental_days', 'daily_rate', 'usage_started_at', 'usage_completed_at', 'payment_reference', 'agreement_number', 'agreement_date']))->map(fn($recipient) => [...$recipient, 'usage_started_at' => optional($recipient['usage_started_at'])->format('Y-m-d'), 'usage_completed_at' => optional($recipient['usage_completed_at'])->format('Y-m-d'), 'agreement_date' => optional($recipient['agreement_date'])->format('Y-m-d')])->values()->all() ?: [['name' => '', 'service_type' => 'Jasa sewa harian', 'quantity' => 1, 'unit' => 'unit', 'rental_days' => 1, 'daily_rate' => 0, 'usage_started_at' => $transactionDateLimit, 'usage_completed_at' => $transactionDateLimit, 'payment_reference' => '', 'agreement_number' => '', 'agreement_date' => $transactionDateLimit]]))
<fieldset x-show="category === 'JASA_LAINNYA'" :disabled="category !== 'JASA_LAINNYA'"
    x-cloak x-data="{
        rows: @js($serviceRecipientRows),
        dragIndex: null,
        total() { return this.rows.reduce((sum, row) => sum + (Number(row.quantity) || 0) * (Number(row.rental_days) || 0) * (Number(row.daily_rate) || 0), 0) },
        add() { this.rows.push({ name: '', service_type: 'Jasa sewa harian', quantity: 1, unit: 'unit', rental_days: 1, daily_rate: 0, usage_started_at: @js($transactionDateLimit), usage_completed_at: @js($transactionDateLimit), payment_reference: '', agreement_number: '', agreement_date: @js($transactionDateLimit) }) },
        move(i, d) {
            const t = i + d;
            if (t < 0 || t >= this.rows.length) return;
            [this.rows[i], this.rows[t]] = [this.rows[t], this.rows[i]];
            this.rows = [...this.rows]
        },
        dropAt(i) {
            if (this.dragIndex === null || this.dragIndex === i) return;
            const [row] = this.rows.splice(this.dragIndex, 1);
            this.rows.splice(i, 0, row);
            this.rows = [...this.rows];
            this.dragIndex = null
        }
    }"
    class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">
                Daftar penerima pembayaran jasa</p>
            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Lampiran pembayaran menjadi
                bukti; total harus sama dengan BKU.</p>
        </div><button type="button" @click="add()"
            class="ui-btn ui-btn-secondary px-3 py-1.5 text-xs font-bold">+ Penerima</button>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-[1100px] w-full text-sm">
            <thead class="text-left text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">
                <tr>
                    <th>No</th>
                    <th>Nama penerima</th>
                    <th>Jenis jasa</th>
                    <th>Jumlah</th>
                    <th>Satuan</th>
                    <th>Hari</th>
                    <th>Tarif/hari</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Ref. bayar</th>
                    <th>PKS</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody><template x-for="(row,index) in rows" :key="index">
                    <tr @dragover.prevent @drop.prevent="dropAt(index)" class="border-t border-[var(--ui-line)]">
                        <td><button type="button" draggable="true"
                                @dragstart="dragIndex=index" @dragend="dragIndex=null"
                                class="cursor-grab px-2">⋮⋮</button><span x-text="index+1"></span></td>
                        <td><input required :name="`service_recipients[${index}][name]`"
                                x-model="row.name" class="ui-input text-sm"></td>
                        <td><input required :name="`service_recipients[${index}][service_type]`"
                                x-model="row.service_type" class="ui-input text-sm"></td>
                        <td><input type="number" :name="`service_recipients[${index}][quantity]`"
                                x-model.number="row.quantity" class="ui-input w-16 text-sm"></td>
                        <td><input :name="`service_recipients[${index}][unit]`"
                                x-model="row.unit" class="ui-input w-16 text-sm"></td>
                        <td><input type="number" step=".5" :name="`service_recipients[${index}][rental_days]`"
                                x-model.number="row.rental_days" class="ui-input w-16 text-sm"></td>
                        <td><input type="number" :name="`service_recipients[${index}][daily_rate]`"
                                x-model.number="row.daily_rate" class="ui-input w-28 text-sm"></td>
                        <td><input type="date" :name="`service_recipients[${index}][usage_started_at]`"
                                x-model="row.usage_started_at" max="{{ $transactionDateLimit }}"
                                class="ui-input text-sm"></td>
                        <td><input type="date" :name="`service_recipients[${index}][usage_completed_at]`"
                                x-model="row.usage_completed_at" max="{{ $transactionDateLimit }}"
                                class="ui-input text-sm"></td>
                        <td><input :name="`service_recipients[${index}][payment_reference]`"
                                x-model="row.payment_reference" class="ui-input text-sm"></td>
                        <td><input :name="`service_recipients[${index}][agreement_number]`"
                                x-model="row.agreement_number" class="ui-input text-sm"></td>
                        <td class="text-right"><button type="button" @click="move(index,-1)"
                                :disabled="index === 0" class="ui-btn ui-btn-ghost px-2">↑</button><button
                                type="button" @click="move(index,1)" :disabled="index === rows.length - 1"
                                class="ui-btn ui-btn-ghost px-2">↓</button><button type="button"
                                @click="rows.splice(index,1)"
                                class="ui-btn ui-btn-ghost px-2 text-rose-700">Hapus</button></td>
                    </tr>
                </template></tbody>
        </table>
    </div>
</fieldset>
