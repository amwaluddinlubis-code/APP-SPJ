<fieldset x-show="category === 'HONOR_PEGAWAI'" :disabled="category !== 'HONOR_PEGAWAI'"
    x-cloak x-data="{
        rows: @js($honorRows),
        transactionTotal: {{ (float) $transaction->gross_amount }},
        dragIndex: null,
        get detailTotal() { return this.rows.reduce((total, row) => total + ((parseInt(row.work_days) || 0) * (Number(row.daily_rate) || 0)), 0); },
        move(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.rows.length) return;
            [this.rows[index], this.rows[target]] = [this.rows[target], this.rows[index]];
            this.rows = [...this.rows];
        },
        dropAt(index) {
            if (this.dragIndex === null || this.dragIndex === index) return;
            const [row] = this.rows.splice(this.dragIndex, 1);
            this.rows.splice(index, 0, row);
            this.rows = [...this.rows];
            this.dragIndex = null;
        }
    }"
    class="mt-3 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">
                Data penerima honor</p>
            <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Gunakan baris ini untuk
                beberapa penerima honor dalam satu transaksi.</p>
        </div>
        <button type="button"
            @click="rows.push({name:'', job_description:'', work_days:1, daily_rate:0, is_receipt_recipient:false, notes:''})"
            class="ui-btn ui-btn-primary px-2.5 py-1.5 text-xs">+ Penerima</button>
    </div>
    <div class="mt-2 overflow-x-auto rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                    <th class="w-10 px-2 py-1.5">No</th>
                    <th class="min-w-44 px-2 py-1">Nama Penerima <span class="text-rose-600">*</span></th>
                    <th class="min-w-52 px-2 py-1">Jabatan/Jenis Honor <span class="text-rose-600">*</span></th>
                    <th class="w-24 px-2 py-1">Bulan/Kali <span class="text-rose-600">*</span></th>
                    <th class="w-32 px-2 py-1">Tarif <span class="text-rose-600">*</span></th>
                    <th class="w-36 px-2 py-1">Penerima Kuitansi</th>
                    <th class="w-36 px-2 py-1">Catatan</th>
                    <th class="w-20 px-2 py-1.5">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, index) in rows" :key="index">
                    <tr @dragover.prevent @drop.prevent="dropAt(index)"
                        :class="dragIndex === index ? 'bg-[var(--ui-surface-soft)]' : ''"
                        class="border-t border-[var(--ui-line)]">
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-2">
                                <button type="button" draggable="true"
                                    @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'"
                                    @dragend="dragIndex = null" title="Seret untuk mengubah urutan"
                                    class="cursor-grab rounded border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-2 py-1 text-[var(--ui-fg-muted)] active:cursor-grabbing">⋮⋮</button><span
                                    class="text-xs font-bold text-[var(--theme-content-accent)]" x-text="index + 1"></span>
                            </div>
                        </td>
                        <td class="px-2 py-2"><input :name="`workers[${index}][name]`" x-model="row.name" required
                                class="ui-input px-2 py-1.5 text-sm" placeholder="Nama penerima"></td>
                        <td class="px-2 py-2"><input :name="`workers[${index}][job_description]`"
                                x-model="row.job_description" required class="ui-input px-2 py-1.5 text-sm"
                                placeholder="Jabatan/jenis honor"></td>
                        <td class="px-2 py-2"><input type="number" min="1" step="1" required
                                :name="`workers[${index}][work_days]`" :value="parseInt(row.work_days) || 1"
                                @input="row.work_days = parseInt($event.target.value) || 1"
                                class="ui-input px-2 py-1.5 text-right text-sm" placeholder="1"></td>
                        <td class="px-2 py-2"><input type="hidden" :name="`workers[${index}][daily_rate]`"
                                :value="row.daily_rate"><input type="text" inputmode="numeric" required
                                :value="new Intl.NumberFormat('en-US').format(Number(row.daily_rate) || 0)"
                                @input="row.daily_rate = Number($event.target.value.replace(/[^0-9]/g, '')); $event.target.value = new Intl.NumberFormat('en-US').format(row.daily_rate)"
                                class="ui-input px-2 py-1.5 text-right text-sm" placeholder="0"></td>
                        <td class="px-2 py-2 text-center"><label
                                class="inline-flex items-center gap-1.5 rounded-md border border-[var(--ui-line)] px-2 py-1.5 text-[var(--ui-fg)]"><input
                                    type="hidden" :name="`workers[${index}][is_receipt_recipient]`" value="0"><input
                                    type="checkbox" :name="`workers[${index}][is_receipt_recipient]`" value="1"
                                    x-model="row.is_receipt_recipient"> Ya</label></td>
                        <td class="px-2 py-2"><input :name="`workers[${index}][notes]`" x-model="row.notes"
                                class="ui-input px-2 py-1.5 text-sm" placeholder="Catatan"></td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1">
                                <button type="button" @click="move(index, -1)" :disabled="index === 0"
                                    title="Naikkan urutan" class="ui-btn ui-btn-secondary px-3 py-1.5 text-sm">↑</button><button
                                    type="button" @click="move(index, 1)" :disabled="index === rows.length - 1"
                                    title="Turunkan urutan" class="ui-btn ui-btn-secondary px-3 py-1.5 text-sm">↓</button><button
                                    type="button" @click="rows.splice(index, 1)"
                                    class="rounded-md border border-rose-200 px-2 py-1.5 text-xs font-bold text-rose-700 dark:border-rose-800 dark:text-rose-300">Hapus</button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div class="mt-3 grid gap-2 sm:grid-cols-3">
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2">
            <p class="text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">Nilai transaksi</p>
            <p class="mt-1 font-mono text-sm font-bold text-[var(--ui-fg-strong)]"
                x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(transactionTotal)"></p>
        </div>
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2">
            <p class="text-[11px] font-bold uppercase text-[var(--ui-fg-muted)]">Total rincian honor</p>
            <p class="mt-1 font-mono text-sm font-bold"
                :class="Math.abs(detailTotal - transactionTotal) < 0.01 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(detailTotal)"></p>
        </div>
        <div class="rounded-lg border px-3 py-2"
            :class="Math.abs(detailTotal - transactionTotal) < 0.01 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
            <p class="text-[11px] font-bold uppercase"
                :class="Math.abs(detailTotal - transactionTotal) < 0.01 ? 'text-emerald-700' : 'text-rose-700'">Kesesuaian</p>
            <p class="mt-1 text-sm font-bold"
                :class="Math.abs(detailTotal - transactionTotal) < 0.01 ? 'text-emerald-800' : 'text-rose-800'"
                x-text="Math.abs(detailTotal-transactionTotal)<0.01?'Sesuai':'Selisih Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(detailTotal-transactionTotal))"></p>
        </div>
    </div>
</fieldset>
