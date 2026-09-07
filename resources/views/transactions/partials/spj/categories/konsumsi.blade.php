<fieldset x-show="category === 'KONSUMSI'" :disabled="category !== 'KONSUMSI'" x-cloak
    x-data="{
        rows: @js($participantRows),
        dapodikRows: @js($dapodikParticipantRows),
        participantCount: {{ (int) old('participant_count', $transaction->participant_count ?: collect($participantRows)->sum('portions')) }},
        dragIndex: null,
        get portionTotal() { return this.rows.reduce((total, row) => total + (parseInt(row.portions) || 0), 0); },
        fillTeachers() {
            this.rows = this.dapodikRows.map(row => ({ ...row }));
            this.participantCount = this.portionTotal;
        },
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
                Data acara & peserta konsumsi</p>
            <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Peserta digunakan sebagai dasar
                porsi konsumsi.</p>
        </div>
        <div class="flex flex-wrap gap-2"><button type="button" @click="fillTeachers()"
                class="ui-btn ui-btn-secondary px-2.5 py-1.5 text-xs font-bold">Ambil semua
                pegawai terdaftar</button><button type="button"
                @click="rows.push({name:'', position:'', portions:1})"
                class="ui-btn ui-btn-primary px-2.5 py-1.5 text-xs font-bold">+ Peserta
                manual</button></div>
    </div>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Nama
                Acara/Rapat <span class="text-rose-600">*</span></label><input required
                name="event_name" value="{{ old('event_name', $transaction->event_name) }}"
                class="ui-input mt-1 text-sm" placeholder="Nama acara/rapat"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tempat
                Pelaksanaan <span class="text-rose-600">*</span></label><input required
                name="event_location"
                value="{{ old('event_location', $transaction->event_location) }}"
                class="ui-input mt-1 text-sm" placeholder="Tempat"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tanggal
                Kegiatan <span class="text-rose-600">*</span></label><input required
                type="date" name="event_date"
                value="{{ old('event_date', $transaction->event_date?->format('Y-m-d') ?: $transactionDateLimit) }}"
                max="{{ $transactionDateLimit }}" class="ui-input mt-1 text-sm"></div>
        <div><label class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Jumlah Peserta
                <span class="text-rose-600">*</span></label><input required type="number"
                min="1" step="1" name="participant_count"
                x-model.number="participantCount" class="ui-input mt-1 text-sm"
                :class="participantCount === portionTotal ? '' :
                    'border-rose-400 dark:border-rose-600 bg-rose-50 dark:bg-rose-950/30 text-rose-900 dark:text-rose-200'">
        </div>
    </div>
    <p x-show="participantCount !== portionTotal"
        class="mt-2 rounded-md border border-rose-300 dark:border-rose-800/60 bg-rose-50 dark:bg-rose-950/40 px-3 py-2 text-xs font-semibold text-rose-700 dark:text-rose-300"
        x-text="'Jumlah peserta harus sama dengan total porsi (' + portionTotal + ').'"></p>
    <div class="mt-2 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--ui-line)] text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                    <th class="px-2 py-1.5">No</th>
                    <th class="px-2 py-1.5">Nama peserta</th>
                    <th class="px-2 py-1.5">Jabatan/Instansi</th>
                    <th class="px-2 py-1.5 text-right">Porsi</th>
                    <th class="px-2 py-1.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, index) in rows" :key="index">
                    <tr @dragover.prevent @drop.prevent="dropAt(index)"
                        :class="dragIndex === index ? 'bg-[var(--ui-surface-base)] shadow-sm' : ''"
                        class="border-b border-[var(--ui-line-subtle)]">
                        <td class="px-2 py-1.5 font-semibold text-[var(--ui-fg-muted)]">
                            <div class="flex items-center gap-2"><button type="button" draggable="true"
                                    @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'"
                                    @dragend="dragIndex = null" title="Seret untuk mengubah urutan"
                                    class="cursor-grab rounded border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-1.5 py-1 text-[var(--ui-fg-muted)] active:cursor-grabbing">⋮⋮</button><span
                                    x-text="index + 1"></span></div>
                        </td>
                        <td class="px-2 py-1.5"><input :name="`participants[${index}][name]`"
                                x-model="row.name" aria-label="Nama peserta"
                                class="ui-input py-1.5 text-sm" placeholder="Nama lengkap"></td>
                        <td class="px-2 py-1.5"><input :name="`participants[${index}][position]`"
                                x-model="row.position" aria-label="Jabatan atau instansi"
                                class="ui-input py-1.5 text-sm" placeholder="Jabatan/instansi"></td>
                        <td class="px-2 py-1.5"><input required type="number" min="1" step="1"
                                :name="`participants[${index}][portions]`" x-model.number="row.portions"
                                aria-label="Jumlah porsi" class="ui-input py-1.5 text-right text-sm w-20"></td>
                        <td class="px-2 py-1.5">
                            <div class="flex items-center justify-end gap-1"><button type="button"
                                    @click="move(index, -1)" :disabled="index === 0" title="Naikkan urutan"
                                    class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-[var(--ui-fg)] disabled:cursor-not-allowed disabled:opacity-35">↑</button><button
                                    type="button" @click="move(index, 1)" :disabled="index === rows.length - 1"
                                    title="Turunkan urutan"
                                    class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-[var(--ui-fg)] disabled:cursor-not-allowed disabled:opacity-35">↓</button><button
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
