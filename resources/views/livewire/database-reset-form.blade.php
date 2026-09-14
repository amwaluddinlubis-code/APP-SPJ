<div data-livewire-reset-form="true" class="space-y-4">
    @if(! $school)
        <x-ui.alert type="warning" title="Belum ada sekolah aktif">
            <p>Pilih sekolah yang akan direset terlebih dahulu.</p>
            <div class="mt-3"><x-ui.button variant="secondary" :href="route('schools.select')">Pilih Sekolah</x-ui.button></div>
        </x-ui.alert>
    @else
        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Sekolah aktif</p>
                    <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $school->name }}</p>
                    <p class="text-sm text-[var(--ui-fg-muted)]">NPSN {{ $school->npsn }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Database tenant</p>
                    <p class="mt-1 break-all font-mono text-sm font-semibold text-[var(--ui-fg)]">{{ $databasePath }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Status: {{ $status }} · Integrity: {{ $integrity }}</p>
                </div>
            </div>
        </section>

        <x-ui.danger-zone title="Aksi permanen" description="Reset menghapus seluruh data tenant, sequence, WAL/SHM, lalu membangun database dari awal.">
            <p class="text-sm font-semibold text-[var(--ui-fg-strong)]">Pastikan backup tersedia sebelum melanjutkan.</p>
            <form wire:submit="resetDatabase" class="mt-4 space-y-4">
                <x-ui.field label="Konfirmasi reset" for="confirmation" hint="Ketik tepat: RESET {{ $school->npsn }}">
                    <x-ui.input id="confirmation" wire:model="confirmation" autocomplete="off" placeholder="RESET {{ $school->npsn }}" required />
                </x-ui.field>
                @error('confirmation')
                    <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
                @enderror
                <div class="flex flex-wrap items-center gap-3 border-t border-[var(--ui-line)] pt-4">
                    <x-ui.button type="submit" variant="danger" wire:confirm="Reset akan menghapus permanen seluruh data database sekolah {{ $school->name }}. Tindakan ini tidak dapat dibatalkan tanpa backup. Lanjutkan?">Reset Database Sekarang</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('database-manager.index')">Batal</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('school-backups.index')">Backup &amp; Pemulihan</x-ui.button>
                </div>
            </form>
        </x-ui.danger-zone>
    @endif
</div>
