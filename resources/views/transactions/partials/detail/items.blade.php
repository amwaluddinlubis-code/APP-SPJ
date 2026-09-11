<section id="rincian-transaksi"
    class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
    <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="font-bold text-[var(--ui-fg-strong)]">Rincian Barang dan Jasa</h2>
            <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Item pembentuk transaksi
                {{ $transaction->no_bukti }}. {{ $transaction->is_siplah ? 'Gunakan nama barang dari metadata SiPLah.' : 'Uraian manual diprioritaskan bila tersedia.' }}</p>
        </div><span
            class="rounded-lg bg-[var(--ui-surface-soft)] px-3 py-2 text-base font-bold text-[var(--theme-content-accent)]">{{ $transaction->items->count() }}
            baris detail</span>
    </div>
    @php
        $siplahItems = collect(data_get($transaction->siplah_metadata, 'siplahResponse.items', []))
            ->merge(data_get($transaction->siplah_metadata, 'siplahResponse.transaction_items', []))
            ->merge(collect(data_get($transaction->siplah_metadata, 'siplahResponse.activities', []))->flatMap(fn ($activity) => $activity['items'] ?? []));
        $normalizeItemName = fn ($value) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $value)));
        $siplahNameForItem = function ($item) use ($siplahItems, $normalizeItemName) {
            $rkasName = $normalizeItemName($item->description);
            $metadataItem = $siplahItems->first(fn ($candidate) => is_array($candidate)
                && $normalizeItemName($candidate['rkas_item_name'] ?? null) === $rkasName);

            return is_array($metadataItem) && filled($metadataItem['siplah_item_name'] ?? null)
                ? $metadataItem['siplah_item_name']
                : null;
        };
        $itemDescriptionsEditable = ! $transaction->spjPackage
            || $transaction->spjPackage->isEditable()
            || $transaction->spjPackage->status === 'NUMBERED';
    @endphp
    @if($transaction->spjPackage?->status === 'NUMBERED')
        <div class="px-5 pt-4">
            <x-ui.alert type="info" title="Koreksi uraian tetap diperbolehkan">
                Hanya nama atau uraian barang/jasa yang dapat diperbaiki pada tahap ini. Nomor SPJ, status paket, dan urutan penomoran tidak berubah.
            </x-ui.alert>
        </div>
    @endif
    <form method="POST" action="{{ route('transactions.spj-descriptions.update', $transaction->id) }}"
        @submit="itemDescriptionsDirty = false">@csrf
        @method('PUT')
        <fieldset @disabled(! $itemDescriptionsEditable) class="disabled:cursor-not-allowed disabled:opacity-60">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-base">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="w-14 px-5 py-3 text-center text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">No</th>
                            <th class="min-w-[320px] px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian Barang/Jasa untuk SPJ</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Rekening</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Volume</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Satuan</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Harga Satuan</th>
                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($transaction->items as $index => $item)
                            <tr class="transition hover:bg-[var(--ui-surface-soft)]">
                                <td class="px-5 py-3.5 text-center text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $index + 1 }}</td>
                                <td class="max-w-xl px-4 py-3.5">
                                    <p class="mb-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->is_siplah ? 'ARKAS: '.$item->description : 'Asli: '.$item->description }}</p>
                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                    <input name="items[{{ $index }}][item_description]"
                                        value="{{ $item->item_description ?: ($transaction->is_siplah ? ($item->siplah_item_name ?: $siplahNameForItem($item) ?: $item->description) : $item->description) }}"
                                        @input="itemDescriptionsDirty = true"
                                        class="ui-input px-3 py-2 text-base" placeholder="{{ $transaction->is_siplah ? 'Nama barang dari SiPLah' : 'Contoh: Buku tulis' }}">
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs text-[var(--theme-content-accent)]">{{ $item->account_code ?: $transaction->account_code ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-right font-medium text-[var(--ui-fg)]">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-3.5 text-[var(--ui-fg)]">{{ $item->unit ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right text-[var(--ui-fg)]">{{ $rupiah($item->unit_price) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right font-bold text-[var(--ui-fg-strong)]">{{ $rupiah($item->amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <p class="font-semibold text-[var(--ui-fg-strong)]">Rincian transaksi belum tersedia.</p>
                                    <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Periksa kembali hasil sinkronisasi BKU untuk nomor bukti ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($transaction->items->isNotEmpty())
                        <tfoot class="border-t-2 border-[var(--ui-line)] bg-[var(--ui-surface-soft)]">
                            <tr>
                                <td colspan="6" class="px-5 py-4 text-right text-base font-bold uppercase tracking-wide text-[var(--ui-fg)]">Total rincian</td>
                                <td class="px-5 py-4 text-right text-base font-bold text-[var(--theme-content-accent)]">{{ $rupiah($totalItems) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if ($transaction->items->isNotEmpty())
                <div class="flex flex-col gap-2 border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                    <p x-show="itemDescriptionsDirty" x-cloak class="text-xs font-semibold text-amber-700">
                        Ada perubahan uraian yang belum tersimpan.
                    </p>
                    <button class="ui-btn ui-btn-primary px-4 py-2 text-sm">Simpan Uraian Barang/Jasa</button>
                </div>
            @endif
        </fieldset>
    </form>
</section>
