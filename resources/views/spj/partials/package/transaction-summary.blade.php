<section class="mx-5 mt-4 overflow-hidden rounded-xl border shadow" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
                        <div class="px-4 py-5 sm:px-5" style="background: linear-gradient(115deg, var(--theme-sidebar-deep), var(--theme-sidebar)); color: var(--text-comfort-on-dark)">
                            <p class="text-[11px] font-bold tracking-[.16em]" style="color: var(--text-comfort-on-dark-muted)">PAKET DOKUMEN SPJ</p>
                            <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h1 class="font-mono text-2xl font-bold sm:text-3xl" style="color: var(--text-comfort-on-dark) !important">{{ $transaction->no_bukti }}</h1>
                                    <p class="mt-1.5 text-base line-clamp-2" style="color: var(--text-comfort-on-dark-muted)">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian transaksi belum tersedia.' }}</p>
                                </div>
                                <div class="rounded-lg px-3 py-2.5 text-left ring-1 lg:text-right" style="background: color-mix(in srgb, var(--ui-surface-base) 10%, transparent); --tw-ring-color: color-mix(in srgb, var(--text-comfort-on-dark) 24%, transparent)">
                                    <p class="text-[11px] font-semibold {{ $package->status === 'CANCELLED' ? 'text-rose-200' : '' }}" @if($package->status !== 'CANCELLED') style="color: var(--text-comfort-on-dark-muted)" @endif>{{ $package->status === 'CANCELLED' ? 'Nomor SPJ dibatalkan' : 'Nomor Dokumen SPJ' }}</p>
                                    <p class="mt-0.5 font-mono text-base font-bold {{ $package->status === 'CANCELLED' ? 'text-rose-100 line-through' : '' }}" @if($package->status !== 'CANCELLED') style="color: var(--text-comfort-on-dark) !important" @endif>{{ $hasActiveSpjNumber ? $activeSpjDocument->document_number : ($package->status === 'CANCELLED' ? ($cancelledSpjDocument?->document_number ?: $package->document_number) : 'Belum ditetapkan') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid divide-y md:grid-cols-3 md:divide-x md:divide-y-0" style="border-color: var(--ui-line)">
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Periode</p><p class="mt-1 text-base font-semibold" style="color: var(--ui-fg)">{{ $package->quarter_code }} · {{ $package->semester_code }}</p></div>
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Penerima</p><p class="mt-1 text-base font-semibold" style="color: var(--ui-fg)">{{ $transaction->recipient_name ?: 'Belum diisi' }}</p></div>
                            <div class="px-4 py-3"><p class="text-[11px] font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Nilai Dibayarkan</p><p class="mt-1 text-base font-semibold text-emerald-700">{{ $rupiah($transaction->net_amount) }}</p></div>
                        </div>
                    </section>

