<div x-show="tab === 'persiapan'" x-transition>
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
        @php($preparationQuery = array_filter(['tab' => 'persiapan', 'month' => $filters['month'] ?? null, 'quarter' => $filters['quarter'] ?? null, 'spj_category' => $filters['spj_category'] ?? null]))
        <nav class="spj-work-queue mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5" aria-label="Pilih pekerjaan SPJ">
            <a href="{{ route('spj.index', array_merge($preparationQuery, ['state' => 'all'])) }}"
                class="spj-work-queue-item"><span>Semua
                    pekerjaan</span><strong>{{ $transactions?->total() ?? 0 }}</strong></a>
            <a href="{{ route('spj.index', array_merge($preparationQuery, ['state' => 'needs_details'])) }}"
                class="spj-work-queue-item"><span>Perlu perhatian:<br>rincian belum ada</span><strong>{{ $workQueueCounts['needs_details'] ?? 0 }}</strong></a>
            <a href="{{ route('spj.index', array_merge($preparationQuery, ['state' => 'unprepared'])) }}"
                class="spj-work-queue-item"><span>Belum
                    dikerjakan</span><strong>{{ $workQueueCounts['unprepared'] ?? 0 }}</strong></a>
            <a href="{{ route('spj.index', array_merge($preparationQuery, ['state' => 'draft'])) }}"
                class="spj-work-queue-item"><span>Perlu
                    dilengkapi</span><strong>{{ $workQueueCounts['draft'] ?? 0 }}</strong></a>
            <a href="{{ route('spj.index', array_merge($preparationQuery, ['state' => 'numbered'])) }}"
                class="spj-work-queue-item"><span>Sudah
                    bernomor</span><strong>{{ $workQueueCounts['numbered'] ?? 0 }}</strong></a>
        </nav>
        <form method="GET" class="spj-filter-bar mt-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
            <input type="hidden" name="tab" value="persiapan">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="Bulan" for="spj-preparation-month"><x-ui.select id="spj-preparation-month"
                        name="month">
                        <option value="">Semua bulan</option>
                        @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $month)
                            <option value="{{ $index + 1 }}" @selected(($filters['month'] ?? null) == $index + 1)>{{ $month }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Triwulan" for="spj-preparation-quarter"><x-ui.select id="spj-preparation-quarter"
                        name="quarter">
                        <option value="">Semua triwulan</option>
                        @foreach (range(1, 4) as $quarter)
                            <option value="{{ $quarter }}" @selected(($filters['quarter'] ?? null) == $quarter)>Triwulan
                                {{ $quarter }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Kategori" for="spj-preparation-category"><x-ui.select id="spj-preparation-category"
                        name="spj_category">
                        <option value="">Semua jenis SPJ</option>
                        @foreach ($spjTypes ?? [] as $type)
                            <option value="{{ $type }}" @selected(($filters['spj_category'] ?? null) === $type)>
                                {{ $spjTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Status" for="spj-preparation-state"><x-ui.select id="spj-preparation-state"
                        name="state">
                        <option value="all">Semua status</option>
                        <option value="needs_details" @selected(($filters['state'] ?? null) === 'needs_details')">Perlu perhatian: rincian belum ada</option>
                        <option value="ready" @selected(($filters['state'] ?? null) === 'ready')">Siap dibuat</option>
                        <option value="unprepared" @selected(($filters['state'] ?? null) === 'unprepared')">Belum dikerjakan</option>
                        <option value="draft" @selected(($filters['state'] ?? null) === 'draft')">Perlu dilengkapi</option>
                        <option value="numbered" @selected(($filters['state'] ?? null) === 'numbered')">Sudah bernomor</option>
                    </x-ui.select></x-ui.field>
            </div>
            <div class="mt-3 flex flex-wrap justify-end gap-2"><x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'persiapan'])">Reset
                    filter</x-ui.button><x-ui.button type="submit">Terapkan filter</x-ui.button></div>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-[var(--ui-line)] text-base">
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
                    <tr class="transition hover:bg-[var(--ui-table-row-hover)]">
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
        <x-page-table-per-page :total="$transactions?->total() ?? 0" />
        <div class="w-full sm:w-auto">{{ $transactions?->links() ?? '' }}</div>
    </div>
</div>
