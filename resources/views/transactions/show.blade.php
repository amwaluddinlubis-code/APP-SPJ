<x-layouts.tailwind-app>
    @php
        $rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $totalItems = $transaction->items->sum('amount');
        $descriptionsFilled = $transaction->items->filter(fn($item) => filled($item->item_description))->count();
        $descriptionsComplete =
            $transaction->items->isNotEmpty() &&
            $transaction->items->every(fn($item) => filled($item->item_description));
        $spjTypeLabel = fn($value) => match (strtoupper((string) $value)) {
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'SPPD' => 'SPPD',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            default => str_replace('_', ' ', (string) $value),
        };
        $sourceStatus = strtoupper((string) ($transaction->source_status ?: 'ACTIVE'));
        $needsAttention = $sourceStatus === 'SOURCE_MISSING' || (bool) $transaction->requires_reconciliation;
    @endphp

    <div class="flex flex-col gap-6" x-data="{ spjDescriptionsDirty: false }">
        @include('transactions.partials.detail.overview-header')
        @include('transactions.partials.detail.overview-status')
        @include('transactions.partials.detail.source-reconciliation')
        @include('transactions.partials.detail.items')
    </div>
</x-layouts.tailwind-app>
