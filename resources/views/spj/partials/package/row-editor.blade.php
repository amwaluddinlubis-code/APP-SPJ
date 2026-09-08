@php
    $primaryNameKey = $primaryNameKey ?? 'name';
    $receiptRecipientName = trim((string) ($transaction->receipt_recipient_name ?? ''));
    $initialPrimaryIndex = collect($rows)->search(function ($row) use ($primaryNameKey, $receiptRecipientName): bool {
        if ($receiptRecipientName === '' || ! is_array($row)) {
            return false;
        }
        return mb_strtolower(trim((string) ($row[$primaryNameKey] ?? ''))) === mb_strtolower($receiptRecipientName);
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
        query: '',
        page: 1,
        perPage: 10,
        addRow() {
            this.rows.push({...this.emptyRow});
            if (this.primaryIndex === null) this.primaryIndex = 0;
            this.page = this.pageCount();
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
            this.page = Math.min(this.page, this.pageCount());
        },
        parseAccounting(value) {
            const digits = String(value ?? '').replace(/[^0-9]/g, '');
            return digits === '' ? 0 : Number(digits);
        },
        formatAccounting(value) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0);
        },
        matchingIndexes() {
            const needle = this.query.trim().toLowerCase();
            return this.rows.map((row, index) => ({ row, index })).filter(({ row }) => {
                if (!needle) return true;
                return Object.values(row || {}).some(value => String(value ?? '').toLowerCase().includes(needle));
            }).map(({ index }) => index);
        },
        pageCount() {
            return Math.max(1, Math.ceil(this.matchingIndexes().length / Number(this.perPage || 10)));
        },
        visible(index) {
            const indexes = this.matchingIndexes();
            const start = (this.page - 1) * Number(this.perPage || 10);
            return indexes.slice(start, start + Number(this.perPage || 10)).includes(index);
        },
        rangeStart() {
            return this.matchingIndexes().length ? ((this.page - 1) * Number(this.perPage || 10)) + 1 : 0;
        },
        rangeEnd() {
            return Math.min(this.page * Number(this.perPage || 10), this.matchingIndexes().length);
        },
        resetPage() { this.page = 1; }
    }"
    class="mt-2"
>
    <input type="hidden" name="primary_recipient_group" value="{{ $prefix }}">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mb-2 flex items-center justify-end">
        <x-ui.button type="button" variant="secondary" x-on:click="addRow()" class="!min-h-8 !px-2.5 !py-1 text-xs">
            <span aria-hidden="true">＋</span> Tambah {{ $rowLabel }}
        </x-ui.button>
    </div>

    <div class="overflow-x-auto rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
        <table data-pagination="none" data-spj-local-pagination="true" class="min-w-full border-collapse text-xs">
            <thead class="bg-[var(--ui-surface-muted)] text-[10px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                <tr>
                    <th class="w-10 whitespace-nowrap px-1.5 py-1.5 text-center">No</th>
                    @foreach($fields as $key => $field)
                        <th class="whitespace-nowrap px-1.5 py-1.5 text-left">{{ $field['label'] }}</th>
                    @endforeach
                    <th class="w-20 whitespace-nowrap px-1.5 py-1.5 text-center">Penerima Utama</th>
                    <th class="w-14 whitespace-nowrap px-1.5 py-1.5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                <template x-for="(row, index) in rows" :key="index">
                    <tr x-show="visible(index)" class="align-middle hover:bg-[var(--ui-surface-soft)]">
                        <td class="px-1.5 py-1 text-center font-mono text-[11px] text-[var(--ui-fg-muted)]" x-text="index + 1"></td>
                        @foreach($fields as $key => $field)
                            @php
                                $type = $field['type'] ?? 'text';
                                $format = $field['format'] ?? null;
                                $cellClass = match ($format ?: $type) {
                                    'accounting' => 'min-w-[8rem] w-32',
                                    'integer', 'number' => 'min-w-[5rem] w-20',
                                    'date' => 'min-w-[8.5rem] w-36',
                                    'textarea' => 'min-w-[13rem]',
                                    default => 'min-w-[9rem]',
                                };
                            @endphp
                            <td class="{{ $cellClass }} px-1 py-1">
                                @if($format === 'accounting')
                                    <input type="hidden" :name="'{{ $prefix }}[' + index + '][{{ $key }}]'" :value="Number(row.{{ $key }}) || 0">
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        :value="formatAccounting(row.{{ $key }})"
                                        @input="row.{{ $key }} = parseAccounting($event.target.value); $event.target.value = formatAccounting(row.{{ $key }})"
                                        class="h-8 w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-right font-mono text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                    >
                                @elseif($format === 'integer')
                                    <input
                                        type="number"
                                        min="{{ $field['min'] ?? 0 }}"
                                        step="1"
                                        :name="'{{ $prefix }}[' + index + '][{{ $key }}]'"
                                        x-model.number="row.{{ $key }}"
                                        class="h-8 w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-right font-mono text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                        @if($field['required'] ?? false) required @endif
                                    >
                                @elseif($type === 'textarea')
                                    <textarea
                                        rows="1"
                                        :name="'{{ $prefix }}[' + index + '][{{ $key }}]'"
                                        x-model="row.{{ $key }}"
                                        class="h-8 min-h-8 w-full resize-y rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 py-1.5 text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                    ></textarea>
                                @else
                                    <input
                                        type="{{ $type }}"
                                        :name="'{{ $prefix }}[' + index + '][{{ $key }}]'"
                                        x-model{{ $type === 'number' ? '.number' : '' }}="row.{{ $key }}"
                                        @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                                        @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
                                        @if($type === 'date' && $transactionDateLimit) max="{{ $transactionDateLimit }}" @endif
                                        @if($field['required'] ?? false) required @endif
                                        class="h-8 w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-xs focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                    >
                                @endif
                            </td>
                        @endforeach
                        <td class="px-1.5 py-1 text-center">
                            <input
                                type="radio"
                                :checked="primaryIndex === index"
                                @change="primaryIndex = index"
                                title="Jadikan {{ $rowLabel }} ini sebagai Penerima Utama"
                                class="h-4 w-4 border-[var(--ui-line-strong)] text-indigo-600 focus:ring-indigo-500"
                            >
                        </td>
                        <td class="px-1.5 py-1 text-center">
                            <button type="button" @click="removeRow(index)" title="Hapus baris" class="inline-flex h-7 w-7 items-center justify-center rounded text-rose-700 transition hover:bg-rose-50 hover:text-rose-900">×</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0">
                    <td colspan="{{ count($fields) + 3 }}" class="px-3 py-5 text-center text-xs text-[var(--ui-fg-muted)]">Belum ada {{ strtolower($rowLabel) }}.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-2 flex flex-col gap-2 border-t border-[var(--ui-line)] pt-2 text-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-1.5">
                <span class="text-[var(--ui-fg-muted)]">Cari</span>
                <input type="search" x-model.debounce.200ms="query" @input="resetPage()" class="h-8 w-44 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 text-xs" placeholder="Filter tabel">
            </label>
            <label class="inline-flex items-center gap-1.5">
                <span class="text-[var(--ui-fg-muted)]">Tampil</span>
                <select x-model.number="perPage" @change="resetPage()" class="h-8 rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 py-0 text-xs">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </label>
        </div>
        <div class="flex items-center justify-between gap-3 sm:justify-end">
            <span class="text-[var(--ui-fg-muted)]"><span x-text="rangeStart()"></span>–<span x-text="rangeEnd()"></span> dari <span x-text="matchingIndexes().length"></span></span>
            <div class="inline-flex items-center gap-1">
                <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" title="Halaman sebelumnya" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">‹</button>
                <span class="min-w-12 text-center font-mono"><span x-text="page"></span>/<span x-text="pageCount()"></span></span>
                <button type="button" @click="page = Math.min(pageCount(), page + 1)" :disabled="page >= pageCount()" title="Halaman berikutnya" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">›</button>
            </div>
        </div>
    </div>
</div>
