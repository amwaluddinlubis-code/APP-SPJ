@php
    $reconciliation = app(\App\Services\SpjSourceReconciliationService::class)->forTransaction($transaction);
    $events = $reconciliation['events'];
    $latest = $reconciliation['latest'];
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

@if($reconciliation['needs_attention'] || $events->isNotEmpty())
    <section class="rounded-2xl border {{ $reconciliation['needs_attention'] ? 'border-amber-300 bg-amber-50/70' : 'border-slate-200 bg-white' }} shadow-sm" data-source-reconciliation>
        <div class="flex flex-col gap-3 border-b border-black/5 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-slate-900">Rekonsiliasi Sumber ARKAS/BKU</h2>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'bg-rose-100 text-rose-700' : ($reconciliation['requires_reconciliation'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">
                        {{ $reconciliation['source_status'] === 'SOURCE_MISSING' ? 'SOURCE MISSING' : ($reconciliation['requires_reconciliation'] ? 'PERLU REKONSILIASI' : 'SUMBER AKTIF') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600">Data manual SPJ tidak ditimpa oleh sinkronisasi. Panel ini hanya menunjukkan perubahan pada data sumber.</p>
            </div>
            @if($latest)
                <p class="text-xs font-semibold text-slate-500">Peristiwa terakhir: {{ $latest->label }}</p>
            @endif
        </div>

        @if($reconciliation['action_hint'])
            <div class="border-b border-black/5 px-5 py-3 text-sm text-amber-900">
                <strong>Tindakan:</strong> {{ $reconciliation['action_hint'] }}
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
            <div class="px-5 py-4 text-sm text-slate-600">{{ $latest->label }} tanpa perubahan nilai sumber yang perlu dibandingkan.</div>
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
