@php
    $workerPrimaryIndex = collect($workerRows)->search(function ($row) use ($transaction): bool {
        if (! is_array($row)) {
            return false;
        }
        if ((bool) ($row['is_receipt_recipient'] ?? false)) {
            return true;
        }
        return filled($transaction->receipt_recipient_name)
            && mb_strtolower(trim((string) ($row['name'] ?? ''))) === mb_strtolower(trim((string) $transaction->receipt_recipient_name));
    });
    if ($workerPrimaryIndex === false) {
        $workerPrimaryIndex = count($workerRows) > 0 ? 0 : null;
    }
    $initialWorkStartedAt = old('work_started_at', $workDetails?->work_started_at?->format('Y-m-d') ?: $transactionDateLimit);
@endphp

<fieldset
    @disabled($selectedSpjType !== 'PEMELIHARAAN')
    @if($selectedSpjType !== 'PEMELIHARAAN') hidden @endif
    data-spj-section="PEMELIHARAAN"
    data-auto-number-spk="{{ $workDetails?->spk_number ?: $transaction->spk_number }}"
    data-auto-number-rab="{{ $workDetails?->rab_number ?: $transaction->rab_number }}"
    x-data="{
        rows: @js(array_values($workerRows)),
        primaryIndex: @js($workerPrimaryIndex),
        workStartedAt: @js($initialWorkStartedAt),
        query: '', page: 1, perPage: 10,
        addWorker() {
            this.rows.push({name:'', job_description:'', work_days:1, daily_rate:0, notes:''});
            if (this.primaryIndex === null) this.primaryIndex = 0;
            this.page = this.pageCount();
        },
        removeWorker(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) this.primaryIndex = null;
            else if (this.primaryIndex === index) this.primaryIndex = Math.min(index, this.rows.length - 1);
            else if (this.primaryIndex > index) this.primaryIndex -= 1;
            this.page = Math.min(this.page, this.pageCount());
        },
        parseAccounting(value) { const digits = String(value ?? '').replace(/[^0-9]/g, ''); return digits === '' ? 0 : Number(digits); },
        accounting(value) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0); },
        total() { return this.rows.reduce((sum, row) => sum + (parseInt(row.work_days) || 0) * (Number(row.daily_rate) || 0), 0); },
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
    <div
        data-spj-maintenance-links
        data-show-url="{{ route('transactions.maintenance-links.show', $transaction->id) }}"
        data-update-url="{{ route('transactions.maintenance-links.update', $transaction->id) }}"
        data-editable="{{ $package->isEditable() ? '1' : '0' }}"
        hidden
    ></div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Work Order Pemeliharaan</h3>
        <button type="button" @click="addWorker()" class="ui-btn ui-btn-secondary !min-h-8 px-2.5 py-1 text-xs font-bold"><span aria-hidden="true">＋</span> Pekerja</button>
    </div>

    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <x-ui.field label="Uraian pekerjaan" required>
                <x-ui.textarea name="work_description" rows="2" required class="!min-h-16 !py-1.5 !text-sm">{{ old('work_description', $workDetails?->work_description ?: $transaction->work_description) }}</x-ui.textarea>
            </x-ui.field>
        </div>
        <x-ui.field label="Lokasi pekerjaan" required>
            <x-ui.input name="work_location" :value="old('work_location', $workDetails?->work_location ?: $transaction->work_location)" required class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tanggal mulai">
            <x-ui.input type="date" name="work_started_at" :value="$initialWorkStartedAt" x-model="workStartedAt" :max="$transactionDateLimit" class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tanggal selesai">
            <x-ui.input type="date" name="work_completed_at" :value="old('work_completed_at', $workDetails?->work_completed_at?->format('Y-m-d') ?: $transactionDateLimit)" x-bind:min="workStartedAt || null" :max="$transactionDateLimit" class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tanggal SPK">
            <x-ui.input type="date" name="spk_date" :value="old('spk_date', $workDetails?->spk_date?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" class="!py-1.5 !text-sm" />
        </x-ui.field>
        <x-ui.field label="Tanggal RAB">
            <x-ui.input type="date" name="rab_date" :value="old('rab_date', $workDetails?->rab_date?->format('Y-m-d') ?: $transactionDateLimit)" :max="$transactionDateLimit" class="!py-1.5 !text-sm" />
        </x-ui.field>
    </div>

    <input type="hidden" name="primary_recipient_group" value="workers">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mt-3 overflow-x-auto rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
        <table data-pagination="none" data-spj-local-pagination="true" class="min-w-full border-collapse text-xs">
            <thead class="bg-[var(--ui-surface-muted)] text-[10px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                <tr>
                    <th class="w-10 px-1.5 py-1.5 text-center">No</th>
                    <th class="min-w-[10rem] px-1.5 py-1.5 text-left">Pekerja</th>
                    <th class="min-w-[12rem] px-1.5 py-1.5 text-left">Uraian Tugas</th>
                    <th class="w-20 px-1.5 py-1.5 text-right">Hari</th>
                    <th class="w-32 px-1.5 py-1.5 text-right">Tarif/Hari</th>
                    <th class="w-32 px-1.5 py-1.5 text-right">Jumlah</th>
                    <th class="w-20 px-1.5 py-1.5 text-center">Penerima Utama</th>
                    <th class="w-14 px-1.5 py-1.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                <template x-for="(row, index) in rows" :key="index">
                    <tr x-show="visible(index)" class="hover:bg-[var(--ui-surface-soft)]">
                        <td class="px-1.5 py-1 text-center font-mono text-[11px]" x-text="index + 1"></td>
                        <td class="px-1 py-1"><input :name="`workers[${index}][name]`" x-model="row.name" class="h-8 w-full rounded border border-[var(--ui-line-strong)] px-2 text-xs" required></td>
                        <td class="px-1 py-1"><input :name="`workers[${index}][job_description]`" x-model="row.job_description" class="h-8 w-full rounded border border-[var(--ui-line-strong)] px-2 text-xs"></td>
                        <td class="px-1 py-1"><input type="number" min="0" step="1" :name="`workers[${index}][work_days]`" x-model.number="row.work_days" class="h-8 w-20 rounded border border-[var(--ui-line-strong)] px-2 text-right font-mono text-xs"></td>
                        <td class="px-1 py-1">
                            <input type="hidden" :name="`workers[${index}][daily_rate]`" :value="Number(row.daily_rate) || 0">
                            <input type="text" inputmode="numeric" :value="accounting(row.daily_rate)" @input="row.daily_rate = parseAccounting($event.target.value); $event.target.value = accounting(row.daily_rate)" class="h-8 w-32 rounded border border-[var(--ui-line-strong)] px-2 text-right font-mono text-xs">
                        </td>
                        <td class="px-2 py-1 text-right font-mono text-xs font-bold" x-text="accounting((parseInt(row.work_days) || 0) * (Number(row.daily_rate) || 0))"></td>
                        <td class="px-1.5 py-1 text-center"><input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" title="Jadikan pekerja ini sebagai Penerima Utama" class="h-4 w-4 border-[var(--ui-line-strong)] text-indigo-600 focus:ring-indigo-500"></td>
                        <td class="px-1.5 py-1 text-center"><button type="button" @click="removeWorker(index)" title="Hapus baris" class="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 hover:bg-rose-50">×</button></td>
                    </tr>
                </template>
            </tbody>
            <tfoot class="bg-[var(--ui-surface-soft)]">
                <tr><td colspan="5" class="px-2 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Total</td><td class="px-2 py-1.5 text-right font-mono text-xs font-bold text-[var(--ui-fg-strong)]" x-text="accounting(total())"></td><td colspan="2"></td></tr>
            </tfoot>
        </table>
    </div>

    <div class="mt-2 flex flex-col gap-2 border-t border-[var(--ui-line)] pt-2 text-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-1.5"><span class="text-[var(--ui-fg-muted)]">Cari</span><input type="search" x-model.debounce.200ms="query" @input="page=1" class="h-8 w-44 rounded border border-[var(--ui-line-strong)] px-2 text-xs" placeholder="Filter tabel"></label>
            <label class="inline-flex items-center gap-1.5"><span class="text-[var(--ui-fg-muted)]">Tampil</span><select x-model.number="perPage" @change="page=1" class="h-8 rounded border border-[var(--ui-line-strong)] px-2 py-0 text-xs"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
        </div>
        <div class="flex items-center justify-between gap-3 sm:justify-end">
            <span class="text-[var(--ui-fg-muted)]"><span x-text="rangeStart()"></span>–<span x-text="rangeEnd()"></span> dari <span x-text="matchingIndexes().length"></span></span>
            <div class="inline-flex items-center gap-1"><button type="button" @click="page=Math.max(1,page-1)" :disabled="page<=1" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">‹</button><span class="min-w-12 text-center font-mono"><span x-text="page"></span>/<span x-text="pageCount()"></span></span><button type="button" @click="page=Math.min(pageCount(),page+1)" :disabled="page>=pageCount()" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">›</button></div>
        </div>
    </div>
</fieldset>
