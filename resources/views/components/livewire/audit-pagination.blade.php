@props(['paginator', 'pageName', 'perPage', 'noun'])
<div
    class="flex flex-col gap-3 border-t border-[var(--ui-line)] px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3 text-[var(--ui-fg-muted)]">
        <span>Menampilkan {{ $paginator->firstItem() ?: 0 }}–{{ $paginator->lastItem() ?: 0 }} dari
            {{ number_format($paginator->total(), 0, ',', '.') }} {{ $noun }}</span>
        <x-ui.select wire:model.live="{{ $perPage }}" class="!min-h-8 !w-auto !py-1 text-xs">
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </x-ui.select>
    </div>
    <div class="flex items-center gap-2">
        {{-- Boolean equivalent of @disabled($paginator->onFirstPage()) is passed to the UI button. --}}
        <x-ui.button type="button" variant="secondary" class="!min-h-8 !px-2 !py-1 text-xs"
            wire:click="previousPage('{{ $pageName }}')" :disabled="$paginator->onFirstPage()">Sebelumnya</x-ui.button>
        <span class="text-xs text-[var(--ui-fg-muted)]">{{ $paginator->currentPage() }} /
            {{ $paginator->lastPage() }}</span>
        {{-- Boolean equivalent of @disabled(! $paginator->hasMorePages()) is passed to the UI button. --}}
        <x-ui.button type="button" variant="secondary" class="!min-h-8 !px-2 !py-1 text-xs"
            wire:click="nextPage('{{ $pageName }}')" :disabled="! $paginator->hasMorePages()">Berikutnya</x-ui.button>
    </div>
</div>
