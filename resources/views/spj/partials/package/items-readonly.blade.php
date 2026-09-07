                            <div class="flex items-center justify-between border-b border-[var(--ui-line)] px-4 py-3">
                                <div><h2 class="text-base font-bold text-slate-800">Rincian Paket</h2><p class="mt-0.5 text-xs text-slate-500">{{ $transaction->items->count() }} item yang akan dipakai dokumen SPJ.</p></div>
                                <span class="hidden sm:inline-flex rounded-full bg-[var(--ui-surface-muted)] px-2.5 py-1 text-xs font-bold text-slate-600">{{ $rupiah($transaction->items->sum('amount')) }}</span>
                            </div>
                            <div class="divide-y divide-[var(--ui-line)]">
                                @forelse($transaction->items as $index => $item)
                                    <div class="flex gap-3 px-4 py-3">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--ui-surface-muted)] text-[11px] font-bold text-slate-500">{{ $index + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-base font-medium leading-tight text-slate-800">{{ $item->item_description ?: $item->description }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                                <span><span class="font-semibold text-slate-600">Volume:</span> {{ $item->quantity }}</span>
                                                <span><span class="font-semibold text-slate-600">Satuan:</span> {{ $item->unit ?: '—' }}</span>
                                                <span><span class="font-semibold text-slate-600">Harga Satuan:</span> {{ $rupiah($item->unit_price) }}</span>
                                                <span><span class="font-semibold text-slate-600">Rekening:</span> {{ $item->account_code ?: $transaction->account_code ?: '—' }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Nilai</p>
                                            <p class="mt-0.5 text-base font-semibold text-slate-800">{{ $rupiah($item->amount) }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-4 py-8 text-center text-base text-slate-500">Tidak ada rincian barang/jasa.</p>
                                @endforelse
                            </div>
