<div>
    <div class="overflow-x-auto p-5"><table data-pagination="server" class="spj-monitoring-table min-w-full divide-y text-base"><thead><tr><th class="px-4 py-3 text-left text-xs font-bold">BUKTI</th><th class="px-4 py-3 text-left text-xs font-bold">URAIAN</th><th class="px-4 py-3 text-left text-xs font-bold">STATUS</th><th class="px-4 py-3 text-right text-xs font-bold">AKSI</th></tr></thead><tbody>@forelse($pendingPaginator ?? [] as $transaction)@php
            $wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false;
            $isFreshPackage = $transaction->spjPackage instanceof \App\Models\SpjFreshPackage;
            $packageUrl = $transaction->spjPackage
                ? ($isFreshPackage
                    ? route('spj.index', ['tab' => 'paket', 'fresh_package_id' => $transaction->spjPackage->id])
                    : route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]))
                : null;
            $checklistUrl = $transaction->spjPackage
                ? ($isFreshPackage ? $packageUrl : route('spj.checklist', $transaction->spjPackage->id))
                : null;
        @endphp<tr wire:key="spj-monitoring-{{ $transaction->id }}" class="spj-monitoring-row transition {{ $wasCancelled ? 'is-cancelled' : '' }}"><td class="px-4 py-3 font-mono font-bold">{{ $transaction->no_bukti }}</td><td class="px-4 py-3 max-w-sm truncate">{{ $transaction->description }}</td><td class="px-4 py-3"><span class="spj-monitoring-status rounded-full border px-2 py-0.5 text-xs font-bold {{ $wasCancelled ? 'is-cancelled' : ($transaction->spjPackage ? 'is-draft' : 'is-empty') }}">{{ $wasCancelled ? 'Dibatalkan — menunggu nomor baru' : ($transaction->spjPackage ? 'Draft — nomor belum ditetapkan' : 'Paket belum disiapkan') }}</span></td><td class="px-4 py-3 text-right">@if($transaction->spjPackage)<a href="{{ $wasCancelled ? $packageUrl : $checklistUrl }}" class="font-bold text-[var(--theme-content-accent)] hover:underline">{{ $wasCancelled ? 'Periksa paket →' : 'Lihat checklist →' }}</a>@else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="font-bold text-[var(--theme-content-accent)] hover:underline">Buka persiapan →</a>@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-[var(--ui-fg-muted)]">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>@endforelse</tbody></table></div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-[var(--ui-line)] px-5 py-4 bg-[var(--ui-surface-soft)]">
        <div class="ui-toolbar-group flex items-center gap-2 text-xs">
            <label for="spj-monitoring-per-page" class="font-semibold" style="color: var(--ui-fg-muted)">Baris</label>
            <x-ui.select id="spj-monitoring-per-page" wire:model.live="pendingPerPage" aria-label="Baris per halaman"
                class="!min-h-9 !w-auto !py-1.5 !text-xs">
                <option value="15">15 baris</option>
                <option value="25">25 baris</option>
                <option value="50">50 baris</option>
                <option value="100">100 baris</option>
            </x-ui.select>
            <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($pendingPaginator?->total() ?? 0, 0, ',', '.') }} data</span>
        </div>
        <x-ui.server-pagination :paginator="$pendingPaginator" noun="transaksi" />
    </div>
</div>
