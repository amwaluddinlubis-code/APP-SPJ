@php
    $participantPrimaryIndex = collect($participantRows)->search(function ($row) use ($transaction): bool {
        return is_array($row)
            && filled($transaction->receipt_recipient_name)
            && mb_strtolower(trim((string) ($row['name'] ?? ''))) === mb_strtolower(trim((string) $transaction->receipt_recipient_name));
    });
    if ($participantPrimaryIndex === false) {
        $participantPrimaryIndex = count($participantRows) > 0 ? 0 : null;
    }
@endphp

<fieldset
    @disabled($selectedSpjType !== 'KONSUMSI')
    @if($selectedSpjType !== 'KONSUMSI') hidden @endif
    data-spj-section="KONSUMSI"
    x-data="{
        keySequence: 0,
        rows: @js(array_values($participantRows)).map((row, index) => ({...row, _key: `saved-${index}`})),
        roster: @js(collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'nip' => $employee->nip, 'nuptk' => $employee->nuptk, 'portions' => 1])->values()->all()),
        participantCount: {{ (int) old('participant_count', count($participantRows) > 0 ? count($participantRows) : $transaction->participant_count) }},
        primaryIndex: @js($participantPrimaryIndex),
        draggedKey: null,
        savedOrder: Alpine.$persist([], 'spj-konsumsi-order-{{ (int) session('active_school_id') }}-{{ (int) $transaction->fiscal_year_id }}'),
        orderSources: @js($consumptionOrderSources ?? []),
        copySourceId: '',
        query: '', page: 1, perPage: 10,
        nextKey(prefix = 'participant') { this.keySequence += 1; return `${prefix}-${Date.now()}-${this.keySequence}`; },
        get listedParticipantCount() { return this.rows.filter(row => String(row.name || '').trim() !== '').length; },
        get portionTotal() { return this.rows.reduce((total, row) => total + (parseInt(row.portions) || 0), 0); },
        syncParticipantCount() { this.participantCount = this.listedParticipantCount; },
        fillRoster() {
            this.rows = this.roster.map(row => ({...row, _key: this.nextKey('roster')}));
            this.primaryIndex = this.rows.length ? 0 : null;
            this.syncParticipantCount();
            this.page = 1;
        },
        addRow() {
            this.rows.push({name:'', position:'', nip:'', nuptk:'', portions:1, _key:this.nextKey()});
            if (this.primaryIndex === null) this.primaryIndex = 0;
            this.page = this.pageCount();
        },
        removeRow(index) {
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            this.rows.splice(index,1);
            if (this.rows.length === 0) this.primaryIndex = null;
            else if (primaryRow && this.rows.includes(primaryRow)) this.primaryIndex = this.rows.indexOf(primaryRow);
            else this.primaryIndex = Math.min(index, this.rows.length - 1);
            this.syncParticipantCount();
            this.page = Math.min(this.page, this.pageCount());
        },
        moveRow(fromIndex, toIndex) {
            if (fromIndex < 0 || toIndex < 0 || fromIndex >= this.rows.length || toIndex >= this.rows.length || fromIndex === toIndex) return;
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            const [moved] = this.rows.splice(fromIndex, 1);
            this.rows.splice(toIndex, 0, moved);
            if (primaryRow) this.primaryIndex = this.rows.indexOf(primaryRow);
        },
        startDrag(index, event) {
            this.draggedKey = this.rows[index]?._key || null;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', this.draggedKey || String(index));
            }
        },
        dropAt(index) {
            if (!this.draggedKey) return;
            const fromIndex = this.rows.findIndex(row => row._key === this.draggedKey);
            this.moveRow(fromIndex, index);
            this.draggedKey = null;
        },
        moveBy(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.rows.length) return;
            this.moveRow(index, target);
        },
        reindexPrimary(primaryRow) { this.primaryIndex = primaryRow ? this.rows.indexOf(primaryRow) : null; },
        applyNameOrder(names) {
            const rank = new Map();
            names.forEach((name, i) => { const k = String(name ?? '').trim().toLowerCase(); if (k && !rank.has(k)) rank.set(k, i); });
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            this.rows = this.rows
                .map((row, i) => ({ row, i }))
                .sort((a, b) => {
                    const ra = rank.get(String(a.row.name ?? '').trim().toLowerCase());
                    const rb = rank.get(String(b.row.name ?? '').trim().toLowerCase());
                    if (ra === undefined && rb === undefined) return a.i - b.i;
                    if (ra === undefined) return 1;
                    if (rb === undefined) return -1;
                    return ra - rb;
                })
                .map(({ row }) => row);
            this.reindexPrimary(primaryRow);
            this.page = 1;
        },
        sortByName() {
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            this.rows.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'id'));
            this.reindexPrimary(primaryRow);
            this.page = 1;
        },
        sortByRoster() { this.applyNameOrder(this.roster.map(row => row.name)); },
        saveOrder() { this.savedOrder = this.rows.map(row => row.name).filter(name => String(name ?? '').trim() !== ''); },
        applySavedOrder() { if (this.savedOrder.length) this.applyNameOrder(this.savedOrder); },
        copyFromPackage() {
            const source = this.orderSources.find(item => String(item.id) === String(this.copySourceId));
            if (source) this.applyNameOrder(source.names);
        },
        matchingIndexes() {
            const needle = this.query.trim().toLowerCase();
            return this.rows.map((row,index) => ({row,index})).filter(({row}) => !needle || Object.values(row || {}).some(value => String(value ?? '').toLowerCase().includes(needle))).map(({index}) => index);
        },
        pageCount() { return Math.max(1, Math.ceil(this.matchingIndexes().length / Number(this.perPage || 10))); },
        visible(index) { const list = this.matchingIndexes(); const start = (this.page - 1) * Number(this.perPage || 10); return list.slice(start, start + Number(this.perPage || 10)).includes(index); },
        rangeStart() { return this.matchingIndexes().length ? ((this.page - 1) * Number(this.perPage || 10)) + 1 : 0; },
        rangeEnd() { return Math.min(this.page * Number(this.perPage || 10), this.matchingIndexes().length); }
    }"
    class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Acara / Daftar Peserta Rapat</h3>
            <p class="mt-0.5 text-[11px] text-[var(--ui-fg-muted)]">Seret handle ⋮⋮ untuk mengubah urutan peserta. Urutan ini disimpan dan dipakai pada daftar peserta.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="fillRoster()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">Ambil Pegawai</button>
            <button type="button" @click="addRow()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold"><span aria-hidden="true">＋</span> Peserta</button>
        </div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Susun cepat:</span>
        <button type="button" @click="sortByName()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">A–Z</button>
        <button type="button" @click="sortByRoster()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">Ikut roster</button>
        <button type="button" @click="saveOrder()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">Simpan urutan</button>
        <button type="button" x-show="savedOrder.length > 0" @click="applySavedOrder()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">Pakai tersimpan (<span x-text="savedOrder.length"></span>)</button>
        <div x-show="orderSources.length > 0" class="flex flex-wrap items-center gap-2">
            <select x-model="copySourceId" class="h-8 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 py-0 text-xs" aria-label="Salin urutan dari paket konsumsi lain">
                <option value="">Salin dari paket…</option>
                <template x-for="source in orderSources" :key="source.id">
                    <option :value="source.id" x-text="source.label"></option>
                </template>
            </select>
            <button type="button" @click="copyFromPackage()" :disabled="!copySourceId" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold disabled:opacity-35">Salin urutan</button>
        </div>
    </div>

    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="Nama Acara/Rapat" for="event_name" required :error="$errors->first('event_name')">
            <x-ui.input id="event_name" name="event_name" :value="old('event_name', $transaction->event_name)" required class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tempat Pelaksanaan" for="event_location" required :error="$errors->first('event_location')">
            <x-ui.input id="event_location" name="event_location" :value="old('event_location', $transaction->event_location)" required class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tanggal Kegiatan" for="event_date" required :error="$errors->first('event_date')">
            <x-ui.input id="event_date" type="date" name="event_date" :value="old('event_date', $transaction->event_date?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" required class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Jumlah Peserta" for="participant_count" required :error="$errors->first('participant_count')">
            <x-ui.input id="participant_count" type="number" min="1" step="1" inputmode="numeric" name="participant_count" x-model.number="participantCount" required class="!py-1.5 text-right font-mono !text-sm" ::class="participantCount === listedParticipantCount ? 'border-[var(--ui-line-strong)]' : 'border-rose-400 bg-rose-50'" />
        </x-ui.field>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1.5 font-semibold text-[var(--ui-fg-strong)]">Peserta terdaftar: <b x-text="listedParticipantCount"></b> orang</span>
        <span class="rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1.5 font-semibold text-[var(--ui-fg-strong)]">Total porsi: <b x-text="portionTotal"></b></span>
        <span class="text-[var(--ui-fg-muted)]">Jumlah porsi boleh berbeda dari jumlah peserta.</span>
    </div>

    <p x-show="participantCount !== listedParticipantCount" class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" x-text="'Jumlah peserta harus sama dengan jumlah nama peserta terdaftar (' + listedParticipantCount + ').'"></p>

    <input type="hidden" name="primary_recipient_group" value="participants">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mt-2 overflow-x-auto rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
        <table data-pagination="none" data-spj-local-pagination="true" class="min-w-full border-collapse text-xs">
            <thead class="bg-[var(--ui-surface-muted)] text-[10px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                <tr>
                    <th class="w-20 px-1.5 py-1.5 text-center">Urut</th>
                    <th class="min-w-[12rem] px-1.5 py-1.5 text-left">Nama Peserta</th>
                    <th class="min-w-[10rem] px-1.5 py-1.5 text-left">Jabatan / Instansi</th>
                    <th class="w-28 px-1.5 py-1.5 text-left">NIP</th>
                    <th class="w-28 px-1.5 py-1.5 text-left">NUPTK</th>
                    <th class="w-20 px-1.5 py-1.5 text-right">Porsi</th>
                    <th class="w-20 px-1.5 py-1.5 text-center">Penerima Utama</th>
                    <th class="w-28 px-1.5 py-1.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                <template x-for="(row,index) in rows" :key="row._key">
                    <tr
                        x-show="visible(index)"
                        @dragover.prevent="if (draggedKey && $event.dataTransfer) $event.dataTransfer.dropEffect = 'move'"
                        @drop.prevent="dropAt(index)"
                        class="transition hover:bg-[var(--ui-table-row-hover)]"
                        :class="draggedKey === row._key ? 'bg-[var(--ui-surface-soft)] opacity-60' : ''"
                    >
                        <td class="px-1.5 py-1 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button
                                    type="button"
                                    draggable="true"
                                    @dragstart.stop="startDrag(index, $event)"
                                    @dragend="draggedKey = null"
                                    @keydown.alt.arrow-up.prevent="moveBy(index, -1)"
                                    @keydown.alt.arrow-down.prevent="moveBy(index, 1)"
                                    :aria-label="`Seret untuk mengubah urutan peserta ${index + 1}`"
                                    title="Seret untuk mengubah urutan · Alt+↑/↓ juga tersedia"
                                    class="cursor-grab rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-1.5 py-1 font-bold text-[var(--ui-fg-muted)] active:cursor-grabbing"
                                >⋮⋮</button>
                                <span class="min-w-5 font-mono text-[11px]" x-text="index+1"></span>
                            </div>
                        </td>
                        <td class="px-1 py-1"><input required :name="`participants[${index}][name]`" x-model="row.name" @input="syncParticipantCount()" class="h-8 w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-xs"></td>
                        <td class="px-1 py-1"><input :name="`participants[${index}][position]`" x-model="row.position" class="h-8 w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-xs"></td>
                        <td class="px-1 py-1"><input :name="`participants[${index}][nip]`" x-model="row.nip" class="h-8 w-28 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 font-mono text-xs"></td>
                        <td class="px-1 py-1"><input :name="`participants[${index}][nuptk]`" x-model="row.nuptk" class="h-8 w-28 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 font-mono text-xs"></td>
                        <td class="px-1 py-1"><input required type="number" min="1" step="1" inputmode="numeric" :name="`participants[${index}][portions]`" x-model.number="row.portions" class="h-8 w-20 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-right font-mono text-xs"></td>
                        <td class="px-1.5 py-1 text-center"><input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" title="Jadikan peserta ini sebagai Penerima Utama" class="h-4 w-4 border-[var(--ui-line-strong)] text-indigo-600 focus:ring-indigo-500"></td>
                        <td class="px-1.5 py-1 text-center"><div class="inline-flex items-center gap-0.5"><button type="button" @click="moveBy(index, -1)" :disabled="index <= 0" title="Pindahkan ke atas" class="inline-flex h-7 w-7 items-center justify-center rounded text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)] disabled:opacity-35">↑</button><button type="button" @click="moveBy(index, 1)" :disabled="index >= rows.length - 1" title="Pindahkan ke bawah" class="inline-flex h-7 w-7 items-center justify-center rounded text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)] disabled:opacity-35">↓</button><button type="button" @click="removeRow(index)" title="Hapus baris" class="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 hover:bg-rose-50">×</button></div></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="mt-2 flex flex-col gap-2 border-t border-[var(--ui-line)] pt-2 text-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-1.5"><span class="text-[var(--ui-fg-muted)]">Cari</span><input type="search" x-model.debounce.200ms="query" @input="page=1" class="h-8 w-44 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-xs" placeholder="Filter tabel"></label>
            <label class="inline-flex items-center gap-1.5"><span class="text-[var(--ui-fg-muted)]">Tampil</span><select x-model.number="perPage" @change="page=1" class="h-8 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 py-0 text-xs"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
        </div>
        <div class="flex items-center justify-between gap-3 sm:justify-end"><span class="text-[var(--ui-fg-muted)]"><span x-text="rangeStart()"></span>–<span x-text="rangeEnd()"></span> dari <span x-text="matchingIndexes().length"></span></span><div class="inline-flex items-center gap-1"><button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">‹</button><span class="min-w-12 text-center font-mono"><span x-text="page"></span>/<span x-text="pageCount()"></span></span><button type="button" @click="page=Math.min(pageCount(),page+1)" :disabled="page>=pageCount()" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">›</button></div></div>
    </div>
</fieldset>
