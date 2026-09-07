<section data-spj-refresh="validation" class="mx-5 mt-5 overflow-hidden rounded-xl border {{ $validationIssues ? 'border-amber-200' : 'border-emerald-200' }} bg-[var(--ui-surface-base)] shadow">
                        <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div><h2 class="text-base font-bold" style="color: var(--ui-fg)">Validasi Sebelum Cetak</h2><p class="mt-0.5 text-base {{ $validationIssues ? 'text-amber-700' : 'text-emerald-700' }}">{{ $validationIssues ? count($validationIssues).' data wajib perlu dilengkapi sebelum PDF dibuat.' : 'Semua data wajib lengkap. Paket siap diunduh sebagai PDF.' }}</p></div>
                            @if($validationIssues)
                                <x-ui.status-badge status="BELUM_LENGKAP" label="Belum siap cetak" />
                            @elseif($package->status === 'CANCELLED')
                                <x-ui.status-badge status="CANCELLED" label="Penomoran dibatalkan" />
                            @elseif($package->status === 'DRAFT')
                                <form method="POST" action="{{ route('spj.ready', $package->id) }}">@csrf<x-ui.button type="submit">Tandai siap dinomori</x-ui.button></form>
                            @else
                                <x-ui.status-badge status="READY" label="Siap dicetak" />
                            @endif
                        </div>
                        @if($validationIssues)<div class="divide-y divide-amber-100 border-t border-amber-100 bg-amber-50/40">@foreach($validationIssues as $issue)<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-base"><div><span class="font-bold text-amber-800">{{ $issue['label'] }}</span><span class="ml-1.5 text-amber-700">{{ $issue['message'] }}</span></div><x-ui.button variant="secondary" :href="$issue['url']" class="min-h-0 px-3 py-1.5 text-xs">Buka transaksi →</x-ui.button></div>@endforeach</div>@endif
                    </section>
