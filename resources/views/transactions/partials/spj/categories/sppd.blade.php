<fieldset x-show="category === 'SPPD'" :disabled="category !== 'SPPD'" x-cloak
    x-data="{
        rows: @js($travelRows),
        employees: @js($employeeOptions),
        dragIndex: null,
        addEmployee() {
            const employee = this.employees.find(item => String(item.id) === String(this.$refs.employeePicker.value));
            if (!employee || this.rows.some(row => row.employee_id === employee.id)) return;
            this.rows.push({ employee_id: employee.id, traveler_name: employee.name, destination: '', purpose: '', assignment_letter_number: '', assignment_letter_date: @js($transactionDateLimit), departure_date: @js($transactionDateLimit), return_date: @js($transactionDateLimit), transport_mode: '', amount: 0, notes: '', position: employee.position });
            this.$refs.employeePicker.value = '';
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
                Pelaksana perjalanan dinas</p>
            <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Satu pembayaran dapat memuat
                lebih dari satu pelaksana.</p>
        </div>
        <div class="flex flex-wrap gap-2"><select x-ref="employeePicker"
                class="ui-select min-w-56 px-2.5 py-1.5 text-xs">
                <option value="">Pilih pegawai terdaftar</option><template x-for="employee in employees" :key="employee.id">
                    <option :value="employee.id"
                        x-text="employee.name + (employee.position ? ' · ' + employee.position : '')"></option>
                </template>
            </select><button type="button" @click="addEmployee()"
                class="ui-btn ui-btn-secondary px-2.5 py-1.5 text-xs font-bold">Ambil pegawai</button><button
                type="button"
                @click="rows.push({traveler_name:'', destination:'', purpose:'', assignment_letter_number:'', assignment_letter_date:@js($transactionDateLimit), departure_date:@js($transactionDateLimit), return_date:@js($transactionDateLimit), transport_mode:'', amount:0, notes:''})"
                class="ui-btn ui-btn-primary px-2.5 py-1.5 text-xs font-bold">+ Manual</button>
        </div>
    </div>
    <div class="mt-2 space-y-2">
        <template x-for="(row, index) in rows" :key="index">
            <div @dragover.prevent @drop.prevent="dropAt(index)"
                :class="dragIndex === index ? 'ring-2 ring-[var(--theme-focus-ring)]' : ''"
                class="grid gap-2 rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-2 md:grid-cols-6">
                <div class="flex items-center gap-2 md:col-span-6"><button type="button" draggable="true"
                        @dragstart="dragIndex = index; $event.dataTransfer.effectAllowed = 'move'"
                        @dragend="dragIndex = null" title="Seret untuk mengubah urutan"
                        class="cursor-grab rounded border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-2 py-1 text-[var(--ui-fg-muted)] active:cursor-grabbing">⋮⋮</button><span
                        class="text-xs font-bold text-[var(--theme-content-accent)]"
                        x-text="'Urutan ' + (index + 1)"></span></div>
                <label class="block md:col-span-2"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Nama
                        Pelaksana <span class="text-rose-600">*</span></span><input
                        :name="`travels[${index}][traveler_name]`" x-model="row.traveler_name"
                        class="ui-input mt-1 text-sm" placeholder="Nama pelaksana" required></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tujuan</span><input
                        :name="`travels[${index}][destination]`" x-model="row.destination"
                        class="ui-input mt-1 text-sm" placeholder="Tujuan"></label>
                <label class="block md:col-span-2"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Maksud
                        Perjalanan</span><input :name="`travels[${index}][purpose]`"
                        x-model="row.purpose" class="ui-input mt-1 text-sm"
                        placeholder="Maksud perjalanan"></label>
                <label class="block md:col-span-2"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">No. Surat
                        Tugas <span class="text-emerald-700 dark:text-emerald-400">(otomatis)</span></span><input
                        readonly :name="`travels[${index}][assignment_letter_number]`"
                        x-model="row.assignment_letter_number"
                        class="ui-input ui-input-readonly mt-1 text-sm"
                        placeholder="Terbit setelah penomoran"></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl Surat
                        Tugas</span><input type="date" :name="`travels[${index}][assignment_letter_date]`"
                        x-model="row.assignment_letter_date" max="{{ $transactionDateLimit }}"
                        class="ui-input mt-1 text-sm"></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Transport</span><input
                        :name="`travels[${index}][transport_mode]`" x-model="row.transport_mode"
                        class="ui-input mt-1 text-sm" placeholder="Transport"></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                        Berangkat</span><input type="date" :name="`travels[${index}][departure_date]`"
                        x-model="row.departure_date" max="{{ $transactionDateLimit }}"
                        class="ui-input mt-1 text-sm"></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Tgl
                        Pulang</span><input type="date" :name="`travels[${index}][return_date]`"
                        x-model="row.return_date" max="{{ $transactionDateLimit }}"
                        class="ui-input mt-1 text-sm"></label>
                <label class="block"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Nilai</span><input
                        type="number" min="0" step="0.01" :name="`travels[${index}][amount]`"
                        x-model="row.amount" class="ui-input mt-1 text-right text-sm" placeholder="Nilai"></label>
                <label class="block md:col-span-2"><span
                        class="text-[11px] font-semibold text-[var(--ui-fg-strong)]">Catatan</span><input
                        :name="`travels[${index}][notes]`" x-model="row.notes"
                        class="ui-input mt-1 text-sm" placeholder="Catatan"></label>
                <div class="flex items-end gap-1"><button type="button" @click="move(index, -1)"
                        :disabled="index === 0" title="Naikkan urutan"
                        class="ui-btn ui-btn-secondary px-3 py-1.5 text-sm font-bold disabled:opacity-35">↑</button><button
                        type="button" @click="move(index, 1)" :disabled="index === rows.length - 1"
                        title="Turunkan urutan"
                        class="ui-btn ui-btn-secondary px-3 py-1.5 text-sm font-bold disabled:opacity-35">↓</button><button
                        type="button" @click="rows.splice(index, 1)"
                        class="rounded-md border border-rose-300 bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40">Hapus</button>
                </div>
            </div>
        </template>
    </div>
</fieldset>
