<div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="font-bold text-[var(--ui-fg-strong)]">3. Hasil Validasi Template</h2>
            <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">VALID siap digunakan. WARNING hanya perlu ditinjau dan tidak menghalangi penggunaan. ERROR harus diperbaiki.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">VALID {{ $validationValidCount }}</span>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">WARNING {{ $validationWarningCount }}</span>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800">ERROR {{ $validationErrorCount }}</span>
            <button type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-3 !py-1.5 text-xs" @click="open = !open" :aria-expanded="open.toString()">
                <span x-text="open ? 'Tutup panel' : 'Buka panel'"></span>
                <x-ui.icon name="chevron-down" size="xs" ::class="open ? 'rotate-180' : ''" />
            </button>
        </div>
    </div>
</div>
