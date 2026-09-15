<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
            <x-ui.field label="Status" for="document-template-status">
                <x-ui.select id="document-template-status" wire:model.live="status">
                    <option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Tidak aktif</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="Kategori SPJ" for="document-template-category">
                <x-ui.select id="document-template-category" wire:model.live="category">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $item)<option value="{{ $item }}">{{ ucwords(strtolower(str_replace('_', ' ', $item))) }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="button" variant="secondary" icon="refresh" wire:click="$refresh">Muat Ulang Daftar</x-ui.button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm" data-pagination="server">
            <thead class="bg-[var(--ui-surface-muted)]"><tr class="text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]"><th class="px-4 py-3">Template</th><th class="px-4 py-3">Format</th><th class="px-4 py-3">Digunakan untuk</th><th class="px-4 py-3 text-center">Status</th><th class="px-4 py-3 text-right">Tindakan</th></tr></thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                @forelse ($templates as $template)
                    @php
                        $templateId = (string) $template->id;
                        $canonicalType = \App\Services\SpjDocumentTypeRegistry::canonical((string) $template->document_type);
                        $isCanonical = array_key_exists((string) $template->document_type, $documentTypes);
                        $templateValidation = $validationResults[$template->id] ?? null;
                        $tableValidationStatus = !empty($templateValidation['errors'] ?? []) ? 'ERROR' : (!empty($templateValidation['warnings'] ?? []) ? 'WARNING' : 'VALID');
                        $siplahScopeLabel = $template->is_siplah === null ? 'Semua channel' : ($template->is_siplah ? 'SiPlah' : 'Non-SiPlah');
                    @endphp
                    <tr wire:key="document-template-{{ $templateId }}" class="odd:bg-[var(--ui-surface-base)] even:bg-[var(--ui-surface-soft)] hover:bg-[var(--ui-table-row-hover)]">
                        <td class="px-4 py-3 align-top"><p class="font-semibold text-[var(--ui-fg-strong)]">{{ $template->name }}</p><div class="mt-1 flex flex-wrap items-center gap-2"><span class="font-mono text-[11px] text-[var(--theme-content-accent)]">{{ $template->document_type }}</span>@if (!$isCanonical)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Legacy</span>@endif<span class="text-[10px] font-bold {{ $tableValidationStatus === 'ERROR' ? 'text-rose-700' : ($tableValidationStatus === 'WARNING' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $tableValidationStatus }}</span><span class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-0.5 text-[10px] font-bold text-[var(--ui-fg)]">{{ $siplahScopeLabel }}</span></div>@if (!$isCanonical && $canonicalType)<p class="mt-1 text-[11px] text-amber-700">Alias lama untuk <span class="font-mono font-semibold">{{ $canonicalType }}</span>.</p>@endif</td>
                        <td class="px-4 py-3 align-top"><span class="rounded bg-[var(--ui-surface-muted)] px-2 py-1 text-[11px] font-bold uppercase text-[var(--ui-fg)]">{{ $template->format }}</span></td>
                        <td class="min-w-[360px] px-4 py-3 align-top"><div class="grid gap-3 sm:grid-cols-2"><div><label for="template-categories-{{ $templateId }}" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kategori SPJ</label><div x-data="{ open: false, search: '', selected: $wire.entangle('mappingCategories.{{ $templateId }}'), options: @js($categoryOptions), get filteredOptions() { return this.options.filter((option) => option.label.toLowerCase().includes(this.search.toLowerCase())); }, toggle(value) { this.selected = this.selected.includes(value) ? this.selected.filter((item) => item !== value) : [...this.selected, value]; } }" class="relative"><button type="button" id="template-categories-{{ $templateId }}" class="ui-select flex w-full items-center justify-between gap-2 text-left !min-h-9 !py-1.5 text-xs" @click="open = !open" @keydown.escape.window="open = false" :aria-expanded="open.toString()"><span class="truncate" x-text="selected.length ? `${selected.length} dipilih` : 'Pilih kategori'">Pilih kategori</span><x-ui.icon name="chevron-down" size="xs" /></button><div x-show="open" x-cloak @click.outside="open = false" class="absolute z-30 mt-1 w-full rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] p-2 shadow-lg"><input type="search" x-model="search" placeholder="Cari kategori..." class="mb-2 w-full rounded-md border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1.5 text-xs text-[var(--ui-fg)] focus:border-[var(--theme-content-accent)] focus:outline-none" @click.stop><div class="max-h-48 overflow-y-auto" role="listbox"><template x-for="option in filteredOptions" :key="option.value"><button type="button" class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-left text-xs text-[var(--ui-fg)] hover:bg-[var(--ui-surface-soft)]" @click="toggle(option.value)"><span x-text="option.label"></span><span x-show="selected.includes(option.value)" class="font-bold text-[var(--theme-content-accent)]">✓</span></button></template><p x-show="filteredOptions.length === 0" class="px-3 py-2 text-xs text-[var(--ui-fg-muted)]">Kategori tidak ditemukan.</p></div></div></div><p class="mt-1 text-[10px] text-[var(--ui-fg-muted)]">Pilih satu atau beberapa.</p></div><div><label for="siplah-scope-{{ $templateId }}" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Channel Paket SPJ</label><x-ui.select id="siplah-scope-{{ $templateId }}" wire:model="mappingScopes.{{ $templateId }}" class="!min-h-9 !py-1.5 text-xs"><option value="all">Semua channel</option><option value="siplah">SiPlah saja</option><option value="non_siplah">Non-SiPlah saja</option></x-ui.select></div></div><p class="mt-1 text-[10px] text-[var(--ui-fg-muted)]">SiPlah/Non-SiPlah dipilih dari field <span class="font-mono">is_siplah</span> transaksi.</p>@if (empty($template->applicable_categories))<p class="mt-2 text-[11px] font-semibold text-emerald-700">Template ini dapat digunakan untuk semua kategori SPJ.</p>@endif</td>
                        <td class="px-4 py-3 text-center align-top"><label class="inline-flex items-center gap-2 text-xs font-bold {{ $mappingActive[$templateId] ? 'text-emerald-700' : 'text-[var(--ui-fg-muted)]' }}"><input type="checkbox" wire:model="mappingActive.{{ $templateId }}" value="1">{{ $mappingActive[$templateId] ? 'Aktif' : 'Tidak aktif' }}</label></td>
                        <td class="px-4 py-3 text-right align-top"><div class="flex flex-wrap justify-end gap-1.5"><x-ui.button variant="secondary" icon="download" iconSize="xs" class="!min-h-0 !px-2 !py-1 text-xs" :href="route('document-templates.download', $template->id)">Unduh</x-ui.button><x-ui.button type="button" icon="save" iconSize="xs" class="!min-h-0 !px-2 !py-1 text-xs" wire:click="saveMapping('{{ $templateId }}')" wire:loading.attr="disabled" wire:target="saveMapping('{{ $templateId }}')">Simpan</x-ui.button></div>@error('applicable_categories')<p class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror @error('siplah_scope')<p class="mt-2 text-xs text-rose-700">{{ $message }}</p>@enderror</td>
                    </tr>
                @empty<tr><td colspan="5" class="px-5 py-12 text-center text-[var(--ui-fg-muted)]">Tidak ada template yang sesuai dengan filter saat ini.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if ($templates->hasPages())<x-ui.server-pagination :paginator="$templates" noun="template" />@endif
</div>
