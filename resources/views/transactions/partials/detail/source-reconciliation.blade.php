@php
    $reconciliation = app(\App\Services\SpjSourceReconciliationService::class)->forTransaction($transaction);
    $events = $reconciliation['events'];
    $latest = $reconciliation['latest'];
    $resolutions = $reconciliation['resolutions'];
    $latestResolution = $reconciliation['latest_resolution'];
    $packageStatus = strtoupper((string) ($transaction->spjPackage?->status ?: 'DRAFT'));
    $packageLocked = in_array($packageStatus, ['NUMBERED', 'FINAL'], true);
    $latestHasChanges = $latest && $latest->changes !== [];
    $formatValue = function ($value) {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    };
@endphp

@if($reconciliation['needs_attention'] || $events->isNotEmpty() || $resolutions->isNotEmpty())
    <section class="rounded-2xl border {{ $reconciliation['needs_attention'] ? 'border-amber-300 bg-amber-50/70' : 'border-slate-200 bg-white' }} shadow-sm" data-source-reconciliation>
        <div class="flex flex-col gap-3 border-b border-black/5 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-slate-900">Rekonsiliasi Sumber ARKAS/BKU</h2>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'bg-rose-100 text-rose-700' : ($reconciliation['requires_reconciliation'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                        {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'SOURCE MISSING' : ($reconciliation['requires_reconciliation'] ? 'PERLU REKONSILIASI' : 'REKONSILIASI SELESAI') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600">Data manual SPJ tidak ditimpa oleh sinkronisasi. Perubahan sumber tetap dicatat sebagai riwayat audit.</p>
            </div>
            @if($latest)
                <p class="text-xs font-semibold text-slate-500">Peristiwa terakhir: {{ $latest->label }}</p>
            @endif
        </div>

        @if($reconciliation['action_hint'])
            <div class="border-b border-black/5 px-5 py-3 text-sm text-amber-900">
                <strong>Tindakan:</strong> {{ $reconciliation['action_hint'] }}
            </div>
        @elseif($latestResolution)
            <div class="border-b border-black/5 bg-emerald-50 px-5 py-3 text-sm text-emerald-900">
                <strong>Penyelesaian terakhir:</strong> {{ $latestResolution->label }}
                <span class="ml-1 text-emerald-700">({{ $latestResolution->resolved_at }})</span>
                @if(filled($latestResolution->notes))
                    <p class="mt-1 text-xs text-emerald-800">Catatan: {{ $latestResolution->notes }}</p>
                @endif
            </div>
        @endif

        @if($latest && $latest->changes !== [])
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/60 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Field sumber berubah</th>
                            <th class="px-5 py-3 text-left">Sebelum</th>
                            <th class="px-5 py-3 text-left">Sesudah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latest->changes as $change)
                            <tr class="border-t border-black/5">
                                <td class="px-5 py-3 font-semibold text-slate-800">{{ $change['label'] }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $formatValue($change['before']) }}</td>
                                <td class="px-5 py-3 text-slate-900">{{ $formatValue($change['after']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($latest)
            <div class="px-5 py-4 text-sm text-slate-700">
                <strong>{{ $latest->label }}.</strong> Perubahan terdeteksi pada metadata/snapshot sumber, tetapi tidak ada perubahan nilai bisnis aktif yang perlu dibandingkan.
            </div>
        @endif

        @if($reconciliation['requires_reconciliation'] && $reconciliation['source_status'] !== 'SOURCE_MISSING' && $latest)
            <div class="border-t border-black/5 bg-white/70 px-5 py-4" data-reconciliation-resolution>
                <h3 class="text-sm font-bold text-slate-900">Penyelesaian Rekonsiliasi</h3>

                @if(!$latestHasChanges)
                    <p class="mt-1 text-sm text-slate-600">Anda sudah memeriksa perubahan sumber dan tidak ada nilai bisnis aktif yang berubah. Tandai sebagai sudah ditinjau untuk menutup status rekonsiliasi.</p>
                    <form method="POST" action="{{ route('transactions.source-reconciliation.resolve', $transaction->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="source_event_id" value="{{ $latest->id }}">
                        <input type="hidden" name="resolution" value="REVIEWED_NO_BUSINESS_CHANGE">
                        <div>
                            <label class="text-xs font-semibold text-slate-700">Catatan (opsional)</label>
                            <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800" placeholder="Contoh: perubahan metadata ARKAS sudah diperiksa dan tidak memengaruhi dokumen SPJ."></textarea>
                        </div>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Tandai Sudah Ditinjau</button>
                    </form>
                @elseif($packageLocked)
                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        Paket berstatus <strong>{{ $packageStatus }}</strong>. Ada perubahan nilai sumber nyata, sehingga rekonsiliasi tidak boleh ditutup langsung. Gunakan workflow pembatalan, reissue, atau revisi resmi bila perubahan ARKAS harus diterapkan pada dokumen.
                    </div>
                @else
                    <p class="mt-1 text-sm text-slate-600">Pilih keputusan setelah membandingkan nilai sumber sebelum dan sesudah. Pilihan ini tidak menghapus riwayat perubahan ARKAS.</p>
                    <form method="POST" action="{{ route('transactions.source-reconciliation.resolve', $transaction->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="source_event_id" value="{{ $latest->id }}">
                        <div class="grid gap-2 lg:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                <input type="radio" name="resolution" value="ACCEPT_SOURCE" required class="mt-1">
                                <span>
                                    <strong class="block text-sm text-slate-900">Terima perubahan sumber ARKAS</strong>
                                    <span class="text-xs text-slate-600">Nilai sumber terbaru diakui sebagai dasar transaksi. Overlay manual SPJ yang masih relevan tetap tidak ditimpa otomatis.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-white p-3">
                                <input type="radio" name="resolution" value="KEEP_OVERLAY" required class="mt-1">
                                <span>
                                    <strong class="block text-sm text-slate-900">Pertahankan overlay SPJ</strong>
                                    <span class="text-xs text-slate-600">Perubahan sumber sudah diperiksa, tetapi isian manual SPJ yang ada sengaja dipertahankan.</span>
                                </span>
                            </label>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700">Catatan (opsional)</label>
                            <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800" placeholder="Tuliskan alasan keputusan rekonsiliasi bila diperlukan."></textarea>
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">Konfirmasi Penyelesaian Rekonsiliasi</button>
                    </form>
                @endif
            </div>
        @endif

        @if($resolutions->isNotEmpty())
            <details class="border-t border-black/5 px-5 py-4">
                <summary class="cursor-pointer text-sm font-bold text-slate-700">Riwayat penyelesaian rekonsiliasi ({{ $resolutions->count() }})</summary>
                <div class="mt-3 space-y-2">
                    @foreach($resolutions as $resolution)
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong>{{ $resolution->label }}</strong>
                                <span>{{ $resolution->resolved_at }}</span>
                            </div>
                            @if(filled($resolution->notes))
                                <p class="mt-1">{{ $resolution->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if($events->count() > 1)
            <details class="border-t border-black/5 px-5 py-4">
                <summary class="cursor-pointer text-sm font-bold text-slate-700">Riwayat perubahan sumber ({{ $events->count() }})</summary>
                <div class="mt-3 space-y-2">
                    @foreach($events as $event)
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong class="text-slate-800">{{ $event->label }}</strong>
                                <span>{{ $event->created_at }}</span>
                            </div>
                            @if($event->changes !== [])
                                <p class="mt-1">{{ collect($event->changes)->pluck('label')->implode(', ') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </section>
@endif
