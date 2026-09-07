<fieldset x-show="category === 'PEMELIHARAAN'" :disabled="category !== 'PEMELIHARAAN'"
    x-cloak x-data="{
        rows: @js($workerRows),
        dragIndex: null,
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
                Work order pemeliharaan</p>
            <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">1 transaksi pemeliharaan → 1
                work order → banyak pekerja.</p>
        </div>
        <button type="button"
            @click="rows.push({name:'', job_description:'', work_days:1, daily_rate:0, is_receipt_recipient:false, notes:''})"
            class="ui-btn ui-btn-primary px-2.5 py-1.5 text-xs font-bold">+ Pekerja</button>
    </div>
    <div class="mt-2 grid gap-2 lg:grid-cols-4">
        <div class="lg:col-span-1">
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Deskripsi
                Pekerjaan <span class="text-rose-600">* Wajib diisi</span></label>
            <input name="work_description"
                value="{{ $workDetails?->work_description ?: $transaction->work_description }}"
                class="ui-input mt-1 text-sm" placeholder="Uraian pekerjaan" required>
        </div>
        <div>
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Lokasi
                Pekerjaan <span class="text-rose-600">* Wajib diisi</span></label>
            <input name="work_location"
                value="{{ $workDetails?->work_location ?: $transaction->work_location }}"
                class="ui-input mt-1 text-sm" placeholder="Lokasi pekerjaan" required>
        </div>
        <div>
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No. SPK <span
                    class="font-normal text-emerald-600 dark:text-emerald-400">(otomatis)</span></label>
            <input readonly name="spk_number"
                value="{{ $workDetails?->spk_number ?: $transaction->spk_number }}"
                class="ui-input ui-input-readonly mt-1 text-sm" placeholder="Terbit setelah penomoran">
        </div>
        <div>
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl SPK</label>
            <input type="date" name="spk_date"
                value="{{ $workDetails?->spk_date?->format('Y-m-d') ?: $transaction->spk_date?->format('Y-m-d') ?: $transactionDateLimit }}"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm">
        </div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No. RAB <span
                    class="font-normal text-emerald-600 dark:text-emerald-400">(otomatis)</span></label><input
                readonly name="rab_number" value="{{ $workDetails?->rab_number }}"
                class="ui-input ui-input-readonly mt-1 text-sm" placeholder="Terbit setelah penomoran"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                RAB</label><input type="date" name="rab_date"
                value="{{ $workDetails?->rab_date?->format('Y-m-d') ?: $transactionDateLimit }}"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm"></div>
        <div>
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                Mulai</label>
            <input type="date" name="work_started_at"
                value="{{ $transaction->work_started_at?->format('Y-m-d') ?: $transactionDateLimit }}"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm">
        </div>
        <div>
            <label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                Selesai</label>
            <input type="date" name="work_completed_at"
                value="{{ $transaction->work_completed_at?->format('Y-m-d') ?: $transactionDateLimit }}"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm">
        </div>
    </div>
    <div class="mt-2 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--ui-line)] text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                    <th class="w-10 px-2 py-1.5">No</th>
                    <th class="min-w-44 px-2 py-1">Nama Pekerja</th>
                    <th class="min-w-52 px-2 py-1">Uraian Pekerjaan</th>
                    <th class="w-24 px-2 py-1">Hari</th>
                    <th class="w-32 px-2 py-1">Tarif</th>
                    <th class="w-36 px-2 py-1">Penerima</th>
                    <th class="min-w-44 px-2 py-1.5">Catatan</th>
                    <th class="w-20 px-2 py-1.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, index) in rows" :key="index">
                    <tr @dragover.prevent @drop.prevent="dropAt(index)"
                        :class="dragIndex === index ? 'bg-[var(--ui-surface-base)] shadow-sm' : ''"
                        class="border-b border-[var(--ui-line-subtle)]">
                        <td class="px-2 py-1 font-semibold text-[var(--ui-fg-muted)]">
                            <div class="flex items-center gap-2">
                                <button type="button" draggable="true"
                                    @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'"
                                    @dragend="dragIndex = null" title="Seret untuk mengubah urutan"
                                    class="cursor-grab rounded border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1 text-[var(--ui-fg-muted)] active:cursor-grabbing">⋮⋮</button><span
                                    x-text="index + 1"></span>
                            </div>
                        </td>
                        <td class="px-2 py-1">
                            <input :name="`workers[${index}][name]`" x-model="row.name"
                                class="ui-input py-1.5 text-sm" placeholder="Nama pekerja">
                        </td>
                        <td class="px-2 py-1">
                            <input :name="`workers[${index}][job_description]`" x-model="row.job_description"
                                class="ui-input py-1.5 text-sm" placeholder="Jenis pekerjaan">
                        </td>
                        <td class="px-2 py-1">
                            <input type="number" min="0" step=".5" :name="`workers[${index}][work_days]`"
                                x-model="row.work_days" class="ui-input py-1.5 text-right text-sm w-20"
                                placeholder="Hari">
                        </td>
                        <td class="px-2 py-1">
                            <input type="hidden" :name="`workers[${index}][daily_rate]`" :value="row.daily_rate"><input
                                type="text" inputmode="numeric"
                                :value="new Intl.NumberFormat('en-US').format(Number(row.daily_rate) || 0)"
                                @input="row.daily_rate = Number($event.target.value.replace(/[^0-9]/g, '')); $event.target.value = new Intl.NumberFormat('en-US').format(row.daily_rate)"
                                class="ui-input py-1.5 text-right text-sm w-28" placeholder="0">
                        </td>
                        <td class="px-2 py-1 text-center">
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs text-[var(--ui-fg)]"><input
                                    type="hidden" :name="`workers[${index}][is_receipt_recipient]`" value="0"><input
                                    type="checkbox" :name="`workers[${index}][is_receipt_recipient]`" value="1"
                                    x-model="row.is_receipt_recipient"
                                    class="rounded border-[var(--ui-line)] text-[var(--theme-action-bg)]"> Ya</span>
                        </td>
                        <td class="px-2 py-1"><input :name="`workers[${index}][notes]`" x-model="row.notes"
                                class="ui-input py-1.5 text-sm" placeholder="Catatan"></td>
                        <td class="px-2 py-1">
                            <div class="flex items-center justify-end gap-1"><button type="button"
                                    @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan"
                                    class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-[var(--ui-fg)] disabled:opacity-35">↑</button><button
                                    type="button" @click="move(index, 1)" :disabled="index === rows.length - 1"
                                    title="Turunkan urutan"
                                    class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-[var(--ui-fg)] disabled:opacity-35">↓</button><button
                                    type="button" @click="rows.splice(index, 1)"
                                    class="rounded-md border border-rose-300 dark:border-rose-800 bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40">Hapus</button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</fieldset>
