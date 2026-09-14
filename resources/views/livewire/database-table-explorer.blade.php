<div data-livewire-explorer="true" data-panel="tables" class="db-panel db-table-single space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="db-eyebrow">Explorer · {{ count($tables) }} tabel</p>
            <h3 class="text-base font-bold text-[var(--ui-fg-strong)]">Daftar tabel database</h3>
            <p class="mt-1 max-w-2xl text-xs leading-5 text-[var(--ui-fg-muted)]">Pilih <strong>Buka</strong> untuk melihat struktur kolom dan contoh data. Semua data bersifat baca-saja.</p>
        </div>
        <label class="w-full sm:max-w-xs">
            <span class="sr-only">Cari tabel</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Cari nama atau kelompok…" class="ui-input w-full">
        </label>
    </div>

    <div class="overflow-hidden rounded-xl border border-[var(--ui-line)]">
        <div class="overflow-x-auto">
            <table data-pagination="server" class="db-data-table w-full min-w-[860px]">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortBy('name')" class="font-inherit">Tabel {{ $sort === 'name' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</button></th>
                        <th>Kelompok</th>
                        <th class="text-right"><button type="button" wire:click="sortBy('rows')" class="font-inherit">Baris {{ $sort === 'rows' ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</button></th>
                        <th class="text-right">Kolom</th>
                        <th><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pageTables as $table)
                        <tr>
                            <td>
                                <p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $table['label'] ?? $table['name'] }}</p>
                                <p class="truncate font-mono text-[11px] text-[var(--ui-fg-muted)]">{{ $table['name'] }}</p>
                                <p class="mt-0.5 line-clamp-2 max-w-md text-[11px] text-[var(--ui-fg-muted)]">{{ $table['blurb'] ?? '' }}</p>
                            </td>
                            <td><span class="db-health-pill">{{ $table['group'] ?? 'Sistem' }}</span></td>
                            <td class="text-right font-mono text-xs">{{ $table['count'] ?? '—' }}</td>
                            <td class="text-right font-mono text-xs">{{ $table['columns'] ?? '—' }}</td>
                            <td class="text-right"><button type="button" wire:click="{{ $openTable === $table['name'] ? 'closeTable' : "openTable('{$table['name']}')" }}" class="ui-btn ui-btn-secondary !min-h-0 !px-2 !py-1 !text-xs">{{ $openTable === $table['name'] ? 'Tutup' : 'Buka' }}</button></td>
                        </tr>
                        @if($openTable === $table['name'] && $detail)
                            <tr>
                                <td colspan="5">
                                    <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 text-sm text-[var(--ui-fg-muted)]">
                                        <p class="mb-3 text-xs leading-5">{{ $detail['meta']['blurb'] ?? '' }}</p>
                                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wide">Struktur kolom ({{ count($detail['columns']) }})</p>
                                        <div class="mb-4 overflow-x-auto"><table class="db-data-table w-full"><thead><tr><th>Nama kolom</th><th>Type</th><th>Keterangan</th></tr></thead><tbody>@foreach($detail['columns'] as $column)<tr><td class="font-mono text-xs">{{ $column['name'] }}</td><td class="font-mono text-xs">{{ $column['type'] }}</td><td class="text-xs">{{ $column['pk'] ? 'Kunci utama' : ($column['required'] ? 'Wajib diisi' : '—') }}</td></tr>@endforeach</tbody></table></div>
                                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wide">Contoh isi (10 pertama dari {{ $detail['total'] }} baris)</p>
                                        @if(empty($detail['rows']))<p class="text-xs">Tabel ini belum memiliki data.</p>@else<div class="max-h-[320px] overflow-auto"><table class="db-data-table w-full"><thead><tr>@foreach(array_keys($detail['rows'][0]) as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach($detail['rows'] as $row)<tr>@foreach($row as $value)<td class="max-w-[220px] truncate font-mono text-xs">{{ is_scalar($value) || $value === null ? ($value ?? 'NULL') : json_encode($value) }}</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada tabel yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="db-table-pagination">
            <span>Menampilkan {{ $total ? (($this->getPage() - 1) * $perPage) + 1 : 0 }}–{{ min($this->getPage() * $perPage, $total) }} dari {{ $total }} tabel</span>
            <div class="ui-pagination-group" aria-label="Navigasi halaman tabel">
                <button type="button" wire:click="previousPage" @disabled($this->getPage() <= 1) class="ui-pagination-control">Sebelumnya</button>
                @for($page = 1; $page <= min(3, $pages); $page++)<button type="button" wire:click="setPage({{ $page }})" class="ui-pagination-control {{ $this->getPage() === $page ? 'is-active' : '' }}">{{ $page }}</button>@endfor
                @if($pages > 6)<span class="ui-pagination-control ui-pagination-ellipsis">…</span>@endif
                @if($pages > 3)@for($page = max(4, $pages - 2); $page <= $pages; $page++)<button type="button" wire:click="setPage({{ $page }})" class="ui-pagination-control {{ $this->getPage() === $page ? 'is-active' : '' }}">{{ $page }}</button>@endfor @endif
                <button type="button" wire:click="nextPage" @disabled($this->getPage() >= $pages) class="ui-pagination-control">Berikutnya</button>
            </div>
        </div>
    </div>
</div>
