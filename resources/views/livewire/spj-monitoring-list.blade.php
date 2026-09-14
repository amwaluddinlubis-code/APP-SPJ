<div>
    <div class="overflow-x-auto p-5"><table data-pagination="server" class="min-w-full divide-y divide-amber-100 text-base"><thead class="bg-amber-50"><tr><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">BUKTI</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">URAIAN</th><th class="px-4 py-3 text-left text-xs font-bold text-amber-800">STATUS</th><th class="px-4 py-3 text-right text-xs font-bold text-amber-800">AKSI</th></tr></thead><tbody class="divide-y divide-amber-100">@forelse($pendingPaginator ?? [] as $transaction)@php
            $wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false;
        @endphp<tr wire:key="spj-monitoring-{{ $transaction->id }}" class="transition {{ $wasCancelled ? 'bg-rose-50/60 hover:bg-rose-50' : 'hover:bg-amber-50/60' }}"><td class="px-4 py-3 font-mono font-bold {{ $wasCancelled ? 'text-rose-800' : 'text-amber-900' }}">{{ $transaction->no_bukti }}</td><td class="px-4 py-3 max-w-sm truncate">{{ $transaction->description }}</td><td class="px-4 py-3"><span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $wasCancelled ? 'border-rose-200 bg-rose-100 text-rose-800' : ($transaction->spjPackage ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-[var(--ui-line)] bg-[var(--ui-surface-muted)] text-slate-500') }}">{{ $wasCancelled ? 'Dibatalkan — menunggu nomor baru' : ($transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan') }}</span></td><td class="px-4 py-3 text-right">@if($transaction->spjPackage)<a href="{{ $wasCancelled ? route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) : route('spj.checklist', $transaction->spjPackage->id) }}" class="font-bold text-indigo-700 hover:underline">{{ $wasCancelled ? 'Periksa paket →' : 'Lihat checklist →' }}</a>@else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="font-bold text-indigo-700 hover:underline">Buka persiapan →</a>@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-emerald-700">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>@endforelse</tbody></table></div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-[var(--ui-line)] px-5 py-4 bg-[var(--ui-surface-soft)]">
        <div class="ui-toolbar-group flex items-center gap-2 text-xs">
            <label for="spj-monitoring-per-page" class="font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
            <select id="spj-monitoring-per-page" wire:model.live="pendingPerPage" aria-label="Baris per halaman"
                class="ui-select !min-h-9 !w-auto !py-1.5 !text-xs">
                <option value="15">15 baris</option>
                <option value="25">25 baris</option>
                <option value="50">50 baris</option>
                <option value="100">100 baris</option>
            </select>
            <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($pendingPaginator?->total() ?? 0, 0, ',', '.') }} data</span>
        </div>
        <div class="w-full sm:w-auto">{{ $pendingPaginator?->links() }}</div>
    </div>
</div>
