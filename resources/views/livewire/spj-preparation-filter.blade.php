@php
    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
        'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
        default => str_replace('_', ' ', (string) $value),
    };
@endphp
<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Antrean persiapan SPJ</h2>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih transaksi, periksa rincian, lalu siapkan paket dokumennya.
                </p>
            </div>
            <p class="max-w-xl text-xs font-medium text-[var(--ui-fg-muted)]">Prioritas: perlu dilengkapi → belum dikerjakan →
                sudah bernomor. Dalam setiap kelompok, tanggal terlama tampil lebih dahulu.</p>
        </div>
        <nav class="spj-work-queue mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5" aria-label="Pilih pekerjaan SPJ">
            <button type="button" wire:click="setQueueState('all')" aria-current="{{ $state === 'all' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Semua
                    pekerjaan</span><strong>{{ $workQueueCounts['all'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('needs_details')" aria-current="{{ $state === 'needs_details' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Perlu perhatian:<br>rincian belum ada</span><strong>{{ $workQueueCounts['needs_details'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('unprepared')" aria-current="{{ $state === 'unprepared' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Belum
                    dikerjakan</span><strong>{{ $workQueueCounts['unprepared'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('draft')" aria-current="{{ $state === 'draft' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Perlu
                    dilengkapi</span><strong>{{ $workQueueCounts['draft'] ?? 0 }}</strong></button>
            <button type="button" wire:click="setQueueState('numbered')" aria-current="{{ $state === 'numbered' ? 'true' : 'false' }}"
                class="spj-work-queue-item w-full cursor-pointer text-left"><span>Sudah
                    bernomor</span><strong>{{ $workQueueCounts['numbered'] ?? 0 }}</strong></button>
        </nav>
        <div class="spj-filter-bar mt-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Bulan" for="spj-preparation-month"><x-ui.select id="spj-preparation-month"
                        wire:model.live="month">
                        <option value="">Semua bulan</option>
                        @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $month)
                            <option value="{{ $index + 1 }}">{{ $month }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Triwulan" for="spj-preparation-quarter"><x-ui.select id="spj-preparation-quarter"
                        wire:model.live="quarter">
                        <option value="">Semua triwulan</option>
                        @foreach (range(1, 4) as $quarter)
                            <option value="{{ $quarter }}">Triwulan
                                {{ $quarter }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Kategori" for="spj-preparation-category"><x-ui.select id="spj-preparation-category"
                        wire:model.live="spj_category">
                        <option value="">Semua jenis SPJ</option>
                        @foreach ($spjTypes ?? [] as $type)
                            <option value="{{ $type }}">
                                {{ $spjTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Status" for="spj-preparation-state"><x-ui.select id="spj-preparation-state"
                        wire:model.live="state">
                        <option value="all">Semua status</option>
                        <option value="needs_details">Perlu perhatian: rincian belum ada</option>
                        <option value="ready">Siap dibuat</option>
                        <option value="unprepared">Belum dikerjakan</option>
                        <option value="draft">Perlu dilengkapi</option>
                        <option value="numbered">Sudah bernomor</option>
                    </x-ui.select></x-ui.field>
            </div>
            <div class="mt-3 flex flex-wrap justify-end gap-2"><x-ui.button type="button" variant="secondary" wire:click="resetFilters">Reset
                    filter</x-ui.button></div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table data-pagination="server" class="min-w-full divide-y divide-[var(--ui-line)] text-base">
            <thead class="bg-[var(--ui-surface-soft)]">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Bukti /
                        Tanggal</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian /
                        Penerima</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kategori /
                        Rincian</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tindakan
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                @forelse($transactions ?? [] as $transaction)
                    <tr wire:key="spj-preparation-{{ $transaction->id }}" class="transition hover:bg-[var(--ui-table-row-hover)]">
                        <td class="px-5 py-4">
                            <p class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $transaction->no_bukti }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                                {{ $transaction->transaction_date?->translatedFormat('d F Y') }}</p>
                        </td>
                        <td class="px-4 py-4">
                            @if ($transaction->spjPackage)
                                <x-ui.status-badge :status="$transaction->spjPackage->document_number ? 'NUMBERED' : 'DRAFT'" :label="$transaction->spjPackage->document_number ? 'Bernomor' : 'Draft paket'" />
                                <p class="mt-1 font-mono text-xs text-[var(--ui-fg-muted)]">
                                    {{ $transaction->spjPackage->document_number ?: 'Perlu dilengkapi' }}</p>
                            @else<x-ui.status-badge status="BELUM_LENGKAP" label="Belum disiapkan" />
                            @endif
                        </td>
                        <td class="max-w-sm px-4 py-4">
                            <p class="truncate font-semibold text-[var(--ui-fg-strong)]">
                                {{ $transaction->payment_description ?: $transaction->description ?: 'Tanpa uraian' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-[var(--ui-fg-muted)]">
                                {{ $transaction->recipient_name ?: 'Penerima belum diisi' }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-xs font-bold text-[var(--theme-content-accent)]">
                                {{ $spjTypeLabel($transaction->spj_category) }}</p>
                            <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->items_count }} rincian</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-right font-semibold text-[var(--ui-fg-strong)]">
                            {{ $rupiah($transaction->gross_amount) }}</td>
                        <td class="px-5 py-4 text-right">
                            @if ($transaction->spjPackage)
                                <x-ui.button variant="secondary" :href="route('spj.index', [
                                    'tab' => 'paket',
                                    'package_id' => $transaction->spjPackage->id,
                                ])">Buka paket →</x-ui.button>
                            @elseif($transaction->items_count)
                            <x-ui.button :href="route('transactions.show', $transaction->id) . '#modul-buat-spj'">Lengkapi &amp; siapkan →</x-ui.button>@else<span
                                    class="text-xs text-[var(--ui-fg-muted)]">Perlu perhatian: rincian belum ada</span>
                            @endif
                        </td>
                    </tr>
                @empty<tr>
                        <td colspan="6" class="px-5 py-14 text-center">
                            <p class="font-semibold text-[var(--ui-fg-strong)]">Belum ada transaksi tersinkron.</p>
                            <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Jalankan Sinkron Semua ARKAS terlebih dahulu.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-[var(--ui-line)] px-5 py-4 bg-[var(--ui-surface-soft)]">
        <div class="ui-toolbar-group flex items-center gap-2 text-xs">
            <label for="spj-preparation-per-page" class="font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
            <x-ui.select id="spj-preparation-per-page" wire:model.live="perPage" aria-label="Baris per halaman"
                class="!min-h-9 !w-auto !py-1.5 !text-xs">
                <option value="15">15 baris</option>
                <option value="25">25 baris</option>
                <option value="50">50 baris</option>
                <option value="100">100 baris</option>
            </x-ui.select>
            <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($transactions?->total() ?? 0, 0, ',', '.') }} data</span>
        </div>
        <x-ui.server-pagination :paginator="$transactions" noun="transaksi" />
    </div>
</div>
