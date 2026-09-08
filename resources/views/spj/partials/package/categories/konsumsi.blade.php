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
        rows: @js(array_values($participantRows)),
        roster: @js(collect($participantRoster ?? [])->map(fn ($employee) => ['name' => $employee->name, 'position' => $employee->position ?: $employee->staff_type, 'portions' => 1])->values()->all()),
        participantCount: {{ (int) old('participant_count', $transaction->participant_count ?: collect($participantRows)->sum('portions')) }},
        primaryIndex: @js($participantPrimaryIndex),
        query: '', page: 1, perPage: 10,
        get portionTotal() { return this.rows.reduce((total, row) => total + (parseInt(row.portions) || 0), 0); },
        fillRoster() { this.rows = this.roster.map(row => ({...row})); this.participantCount = this.portionTotal; this.primaryIndex = this.rows.length ? 0 : null; this.page = 1; },
        addRow() { this.rows.push({name:'', position:'', portions:1}); if (this.primaryIndex === null) this.primaryIndex = 0; this.participantCount = this.portionTotal; this.page = this.pageCount(); },
        removeRow(index) {
            this.rows.splice(index,1);
            if (this.rows.length === 0) this.primaryIndex = null;
            else if (this.primaryIndex === index) this.primaryIndex = Math.min(index, this.rows.length - 1);
            else if (this.primaryIndex > index) this.primaryIndex -= 1;
            this.participantCount = this.portionTotal;
            this.page = Math.min(this.page, this.pageCount());
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
    class="rounded-lg border border-sky-200 bg-sky-50/60 p-3"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-sky-900">Acara / Daftar Peserta</h3>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="fillRoster()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold">Ambil Pegawai</button>
            <button type="button" @click="addRow()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold"><span aria-hidden="true">＋</span> Peserta</button>
        </div>
    </div>

    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-xs font-semibold text-sky-900">Nama Acara/Rapat <span class="text-rose-600">*</span><input required name="event_name" value="{{ old('event_name', $transaction->event_name) }}" class="mt-1 h-8 w-full rounded-md border border-sky-200 px-2 text-sm"></label>
        <label class="text-xs font-semibold text-sky-900">Tempat Pelaksanaan <span class="text-rose-600">*</span><input required name="event_location" value="{{ old('event_location', $transaction->event_location) }}" class="mt-1 h-8 w-full rounded-md border border-sky-200 px-2 text-sm"></label>
        <label class="text-xs font-semibold text-sky-900">Tanggal Kegiatan <span class="text-rose-600">*</span><input required type="date" name="event_date" value="{{ old('event_date', $transaction->event_date?->format('Y-m-d') ?: $transactionDateLimit) }}" max="{{ $transactionDateLimit }}" class="mt-1 h-8 w-full rounded-md border border-sky-200 px-2 text-sm"></label>
        <label class="text-xs font-semibold text-sky-900">Jumlah Peserta <span class="text-rose-600">*</span><input required type="number" min="1" step="1" name="participant_count" x-model.number="participantCount" class="mt-1 h-8 w-full rounded-md border px-2 text-right font-mono text-sm" :class="participantCount === portionTotal ? 'border-sky-200' : 'border-rose-400 bg-rose-50'"></label>
    </div>

    <p x-show="participantCount !== portionTotal" class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" x-text="'Jumlah peserta harus sama dengan total porsi (' + portionTotal + ').'"></p>

    <input type="hidden" name="primary_recipient_group" value="participants">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mt-2 overflow-x-auto rounded-md border border-sky-200 bg-[var(--ui-surface-base)]">
        <table data-pagination="none" data-spj-local-pagination="true" class="min-w-full border-collapse text-xs">
            <thead class="bg-sky-50 text-[10px] font-bold uppercase tracking-wide text-sky-800">
                <tr>
                    <th class="w-10 px-1.5 py-1.5 text-center">No</th>
                    <th class="min-w-[12rem] px-1.5 py-1.5 text-left">Nama Peserta</th>
                    <th class="min-w-[10rem] px-1.5 py-1.5 text-left">Jabatan / Instansi</th>
                    <th class="w-20 px-1.5 py-1.5 text-right">Porsi</th>
                    <th class="w-20 px-1.5 py-1.5 text-center">Penerima Utama</th>
                    <th class="w-14 px-1.5 py-1.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sky-100">
                <template x-for="(row,index) in rows" :key="index">
                    <tr x-show="visible(index)" class="hover:bg-sky-50/60">
                        <td class="px-1.5 py-1 text-center font-mono text-[11px]" x-text="index+1"></td>
                        <td class="px-1 py-1"><input required :name="`participants[${index}][name]`" x-model="row.name" class="h-8 w-full rounded border border-sky-200 px-2 text-xs"></td>
                        <td class="px-1 py-1"><input :name="`participants[${index}][position]`" x-model="row.position" class="h-8 w-full rounded border border-sky-200 px-2 text-xs"></td>
                        <td class="px-1 py-1"><input required type="number" min="1" step="1" :name="`participants[${index}][portions]`" x-model.number="row.portions" @input="participantCount = portionTotal" class="h-8 w-20 rounded border border-sky-200 px-2 text-right font-mono text-xs"></td>
                        <td class="px-1.5 py-1 text-center"><input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" title="Jadikan peserta ini sebagai Penerima Utama" class="h-4 w-4 border-sky-300 text-indigo-600 focus:ring-indigo-500"></td>
                        <td class="px-1.5 py-1 text-center"><button type="button" @click="removeRow(index)" title="Hapus baris" class="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 hover:bg-rose-50">×</button></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="mt-2 flex flex-col gap-2 border-t border-sky-200 pt-2 text-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-1.5"><span class="text-sky-800">Cari</span><input type="search" x-model.debounce.200ms="query" @input="page=1" class="h-8 w-44 rounded border border-sky-200 px-2 text-xs" placeholder="Filter tabel"></label>
            <label class="inline-flex items-center gap-1.5"><span class="text-sky-800">Tampil</span><select x-model.number="perPage" @change="page=1" class="h-8 rounded border border-sky-200 px-2 py-0 text-xs"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
        </div>
        <div class="flex items-center justify-between gap-3 sm:justify-end"><span class="text-sky-800"><span x-text="rangeStart()"></span>–<span x-text="rangeEnd()"></span> dari <span x-text="matchingIndexes().length"></span></span><div class="inline-flex items-center gap-1"><button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="h-8 rounded border border-sky-200 px-2 font-bold disabled:opacity-35">‹</button><span class="min-w-12 text-center font-mono"><span x-text="page"></span>/<span x-text="pageCount()"></span></span><button type="button" @click="page=Math.min(pageCount(),page+1)" :disabled="page>=pageCount()" class="h-8 rounded border border-sky-200 px-2 font-bold disabled:opacity-35">›</button></div></div>
    </div>
</fieldset>
