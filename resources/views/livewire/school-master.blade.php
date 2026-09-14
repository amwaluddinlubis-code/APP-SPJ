<div class="space-y-4">
    <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Cari sekolah, kode, atau NPSN..." />
    <div class="divide-y divide-[var(--ui-line)] rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)]">
        @forelse($schools as $school)
            <div wire:key="school-{{ $school->id }}" class="px-4 py-3"><p class="font-bold text-[var(--ui-fg-strong)]">{{ $school->name }}</p><p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Kode {{ $school->school_code ?: '—' }} · NPSN {{ $school->npsn }} · {{ $school->regency ?: 'Kabupaten belum diisi' }}</p><span class="mt-2 inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Database: {{ $school->databaseRecord?->status ?? 'Belum dibuat' }}</span></div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-[var(--ui-fg-muted)]">Belum ada sekolah yang cocok.</div>
        @endforelse
    </div>
</div>
