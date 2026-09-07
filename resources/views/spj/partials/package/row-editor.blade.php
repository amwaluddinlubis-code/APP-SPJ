<div x-data="{ rows: @js($rows), emptyRow: @js($emptyRow), addRow() { this.rows.push({...this.emptyRow}); } }" class="mt-3 space-y-3">
    <template x-for="(row, index) in rows" :key="index">
        <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3">
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-sm font-semibold text-[var(--ui-fg-strong)]" x-text="'{{ $rowLabel }} ' + (index + 1)"></p>
                <x-ui.button type="button" variant="ghost" x-on:click="rows.splice(index, 1)">Hapus</x-ui.button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($fields as $key => $field)
                    <x-ui.field :label="$field['label']">
                        @if(($field['type'] ?? 'text') === 'textarea')
                            <x-ui.textarea x-bind:name="'{{ $prefix }}[' + index + '][{{ $key }}]'" x-model="row.{{ $key }}" rows="2" />
                        @else
                            <x-ui.input :type="$field['type'] ?? 'text'" x-bind:name="'{{ $prefix }}[' + index + '][{{ $key }}]'" x-model="row.{{ $key }}" :min="$field['min'] ?? null" :step="$field['step'] ?? null" :max="($field['type'] ?? '') === 'date' ? $transactionDateLimit : null" :required="$field['required'] ?? false" />
                        @endif
                    </x-ui.field>
                @endforeach
            </div>
        </div>
    </template>
    <x-ui.button type="button" variant="secondary" x-on:click="addRow()">Tambah {{ $rowLabel }}</x-ui.button>
</div>
