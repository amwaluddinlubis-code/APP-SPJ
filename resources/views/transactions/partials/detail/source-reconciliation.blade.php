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
    <section class="rounded-2xl border {{ $reconciliation['needs_attention'] ? 'border-amber-300 bg-amber-50/70' : 'border-[var(--ui-line)] bg-[var(--ui-surface-base)]' }} shadow-sm" data-source-reconciliation>
        <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Rekonsiliasi Sumber ARKAS/BKU</h2>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'bg-rose-100 text-rose-700' : ($reconciliation['requires_reconciliation'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                        {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'SOURCE MISSING' : ($reconciliation['requires_reconciliation'] ? 'PERLU REKONSILIASI' : 'REKONSILIASI SELESAI') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Data manual SPJ tidak ditimpa oleh sinkronisasi. Perubahan sumber tetap dicatat sebagai riwayat audit.</p>
            </div>
            @if($latest)
                <p class="text-xs font-semibold text-[var(--ui-fg-muted)]">Peristiwa terakhir: {{ $latest->label }}</p>
            @endif
        </div>

        @if($reconciliation['action_hint'])
            <div class="border-b border-[var(--ui-line)] px-5 py-3 text-sm text-amber-900">
                <strong>Tindakan:</strong> {{ $reconciliation['action_hint'] }}
            </div>
        @elseif($latestResolution)
            <div class="border-b border-[var(--ui-line)] bg-emerald-50 px-5 py-3 text-sm text-emerald-900">
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
                    <thead class="bg-[var(--ui-surface-soft)] text-xs uppercase tracking-wide text-[var(--ui-fg-muted)]">
                        <tr>
                            <th class="px-5 py-3 text-left">Field sumber berubah</th>
                            <th class="px-5 py-3 text-left">Sebelum</th>
                            <th class="px-5 py-3 text-left">Sesudah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latest->changes as $change)
                            <tr class="border-t border-[var(--ui-line)]">
                                <td class="px-5 py-3 font-semibold text-[var(--ui-fg-strong)]">{{ $change['label'] }}</td>
                                <td class="px-5 py-3 text-[var(--ui-fg-muted)]">{{ $formatValue($change['before']) }}</td>
                                <td class="px-5 py-3 text-[var(--ui-fg-strong)]">{{ $formatValue($change['after']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($latest)
            <div class="px-5 py-4 text-sm text-[var(--ui-fg)]">
                <strong>{{ $latest->label }}.</strong> Perubahan terdeteksi pada metadata/snapshot sumber, tetapi tidak ada perubahan nilai bisnis aktif yang perlu dibandingkan.
            </div>
        @endif

        @if($reconciliation['requires_reconciliation'] && $reconciliation['source_status'] !== 'SOURCE_MISSING' && $latest)
            <div class="border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4" data-reconciliation-resolution>
                <h3 class="text-sm font-bold text-[var(--ui-fg-strong)]">Penyelesaian Rekonsiliasi</h3>

                @if(!$latestHasChanges)
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Anda sudah memeriksa perubahan sumber dan tidak ada nilai bisnis aktif yang berubah. Tandai sebagai sudah ditinjau untuk menutup status rekonsiliasi.</p>
                    <form method="POST" action="{{ route('transactions.source-reconciliation.resolve', $transaction->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="source_event_id" value="{{ $latest->id }}">
                        <input type="hidden" name="resolution" value="REVIEWED_NO_BUSINESS_CHANGE">
                        <div>
                            <x-ui.field label="Catatan (opsional)">
                                <x-ui.textarea name="notes" rows="2" maxlength="2000" placeholder="Contoh: perubahan metadata ARKAS sudah diperiksa dan tidak memengaruhi dokumen SPJ." />
                            </x-ui.field>
                        </div>
                        <x-ui.button type="submit" variant="success">Tandai Sudah Ditinjau</x-ui.button>
                    </form>
                @elseif($packageLocked)
                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        Paket berstatus <strong>{{ $packageStatus }}</strong>. Ada perubahan nilai sumber nyata, sehingga rekonsiliasi tidak boleh ditutup langsung. Gunakan workflow pembatalan, reissue, atau revisi resmi bila perubahan ARKAS harus diterapkan pada dokumen.
                    </div>
                @else
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Pilih keputusan setelah membandingkan nilai sumber sebelum dan sesudah. Pilihan ini tidak menghapus riwayat perubahan ARKAS.</p>
                    <form method="POST" action="{{ route('transactions.source-reconciliation.resolve', $transaction->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="source_event_id" value="{{ $latest->id }}">
                        <div class="grid gap-2 lg:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3">
                                <input type="radio" name="resolution" value="ACCEPT_SOURCE" required class="mt-1">
                                <span>
                                    <strong class="block text-sm text-[var(--ui-fg-strong)]">Terima perubahan sumber ARKAS</strong>
                                    <span class="text-xs text-[var(--ui-fg-muted)]">Nilai sumber terbaru diakui sebagai dasar transaksi. Overlay manual SPJ yang masih relevan tetap tidak ditimpa otomatis.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-3">
                                <input type="radio" name="resolution" value="KEEP_OVERLAY" required class="mt-1">
                                <span>
                                    <strong class="block text-sm text-[var(--ui-fg-strong)]">Pertahankan overlay SPJ</strong>
                                    <span class="text-xs text-[var(--ui-fg-muted)]">Perubahan sumber sudah diperiksa, tetapi isian manual SPJ yang ada sengaja dipertahankan.</span>
                                </span>
                            </label>
                        </div>
                        <div>
                            <x-ui.field label="Catatan (opsional)">
                                <x-ui.textarea name="notes" rows="2" maxlength="2000" placeholder="Tuliskan alasan keputusan rekonsiliasi bila diperlukan." />
                            </x-ui.field>
                        </div>
                        <x-ui.button type="submit">Konfirmasi Penyelesaian Rekonsiliasi</x-ui.button>
                    </form>
                @endif
            </div>
        @endif

        @if($resolutions->isNotEmpty())
            <details class="border-t border-[var(--ui-line)] px-5 py-4">
                <summary class="cursor-pointer text-sm font-bold text-[var(--ui-fg)]">Riwayat penyelesaian rekonsiliasi ({{ $resolutions->count() }})</summary>
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
            <details class="border-t border-[var(--ui-line)] px-5 py-4">
                <summary class="cursor-pointer text-sm font-bold text-[var(--ui-fg)]">Riwayat perubahan sumber ({{ $events->count() }})</summary>
                <div class="mt-3 space-y-2">
                    @foreach($events as $event)
                        <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 text-xs text-[var(--ui-fg-muted)]">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong class="text-[var(--ui-fg-strong)]">{{ $event->label }}</strong>
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
