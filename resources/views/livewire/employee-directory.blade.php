<div class="space-y-6">
    <x-page-header title="Pegawai" subtitle="Satu master pegawai untuk data ARKAS, Dapodik, dan input operator." kicker="Master Pegawai Terpadu">
        <x-slot:actions>
            @if(auth()->user()->isAdministrator())<x-ui.button variant="secondary" :href="route('dapodik.index')">Sinkron Dapodik</x-ui.button>@endif
            @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))<x-ui.button :href="route('employees.create')"><x-ui.icon name="plus" size="sm" /> Tambah pegawai</x-ui.button>@endif
        </x-slot:actions>
        <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-5"><x-stat-item label="Total Data" :value="\App\Models\Employee::count()" hint="Satu row per pegawai" /><x-stat-item label="Aktif" :value="\App\Models\Employee::where('is_active', true)->count()" hint="Pegawai aktif" /><x-stat-item label="ARKAS" :value="\App\Models\Employee::fromArkas()->count()" hint="Dari ARKAS" /><x-stat-item label="Dapodik" :value="\App\Models\Employee::fromDapodik()->count()" hint="Dari Dapodik" /><x-stat-item label="Manual" :value="\App\Models\Employee::manualOnly()->count()" hint="Input manual" /></div>
    </x-page-header>

    <x-ui.form-section
        title="Daftar pegawai"
        description="Gunakan pencarian dan filter seperlunya. Perubahan diterapkan langsung tanpa memuat ulang halaman."
        class="overflow-hidden"
    >
        <div class="grid gap-4 lg:grid-cols-12">
            <x-ui.field label="Pencarian" for="employee-search" hint="Nama, NIP, NIK, atau NUPTK." class="lg:col-span-5">
                <x-ui.input id="employee-search" wire:model.live.debounce.300ms="search" placeholder="Contoh: Budi atau 19800101" />
            </x-ui.field>

            <x-ui.field label="Sumber" for="employee-source" class="lg:col-span-2">
                <x-ui.select id="employee-source" wire:model.live="source">
                    <option value="">Semua sumber</option>
                    <option value="ARKAS">ARKAS</option>
                    <option value="DAPODIK">Dapodik</option>
                    <option value="MANUAL">Manual</option>
                </x-ui.select>
            </x-ui.field>

            <x-ui.field label="Status" for="employee-status" class="lg:col-span-2">
                <x-ui.select id="employee-status" wire:model.live="status">
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Tidak aktif</option>
                </x-ui.select>
            </x-ui.field>

            <x-ui.field label="Baris" for="employee-per-page" class="lg:col-span-1">
                <x-ui.select id="employee-per-page" wire:model.live="perPage">
                    @foreach ([15, 25, 50, 100] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            @if($search !== '' || $source !== '' || $status !== '')
                <div class="lg:col-span-2">
                    <span class="block text-sm font-semibold text-transparent select-none" aria-hidden="true">&nbsp;</span>
                    <div class="mt-1.5">
                        <x-ui.button type="button" variant="secondary" wire:click="resetFilters" icon="close" class="w-full">Hapus Saringan</x-ui.button>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-5 hidden md:block">
            <x-ui.table min-width="920px" pagination="external">
                <thead>
                    <tr><th>Pegawai</th><th>Identitas</th><th>Kepegawaian</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr wire:key="employee-row-{{ $employee->id }}">
                            <td>
                                <div class="font-semibold text-[var(--ui-fg-strong)]">{{ $employee->name }}</div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <x-ui.badge variant="neutral">{{ $employee->source_label }}</x-ui.badge>
                                    @if($employee->operator_locked)
                                        <x-ui.badge variant="warning">Dikoreksi operator</x-ui.badge>
                                    @endif
                                    <x-ui.badge :variant="$employee->is_active ? 'success' : 'danger'">{{ $employee->is_active ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge>
                                </div>
                            </td>
                            <td class="text-[var(--ui-fg)]"><div>NIP: <span class="font-medium text-[var(--ui-fg-strong)]">{{ $employee->nip ?: '—' }}</span></div><div class="mt-1 text-xs text-[var(--ui-fg-muted)]">NUPTK: {{ $employee->nuptk ?: '—' }}</div><div class="mt-1 text-xs text-[var(--ui-fg-muted)]">NIK: {{ $employee->nik ? '••••'.substr($employee->nik, -4) : '—' }}</div></td>
                            <td><div class="font-medium text-[var(--ui-fg-strong)]">{{ $employee->position ?: 'Belum tercatat' }}</div><div class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ collect([$employee->staff_type, $employee->employment_status])->filter()->join(' · ') ?: '—' }}</div></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    <x-ui.button variant="secondary" :href="route('employees.show', $employee->id)" class="text-xs">Detail</x-ui.button>
                                    <x-ui.button :href="route('employees.edit', $employee->id)" class="text-xs">Ubah</x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-cell"><p class="font-semibold text-[var(--ui-fg-strong)]">Data pegawai tidak ditemukan.</p><p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Sinkronkan ARKAS/Dapodik atau ubah kriteria pencarian.</p></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] md:hidden">
            @forelse($employees as $employee)
                <article wire:key="employee-card-{{ $employee->id }}" class="border-b border-[var(--ui-line)] p-4 last:border-b-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><h3 class="truncate font-bold text-[var(--ui-fg-strong)]">{{ $employee->name }}</h3><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">NUPTK {{ $employee->nuptk ?: '—' }} · {{ $employee->position ?: 'Jabatan belum tercatat' }}</p></div>
                        <x-ui.badge :variant="$employee->is_active ? 'success' : 'danger'">{{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-3"><span class="text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $employee->source_label }}</span><div class="flex gap-2"><x-ui.button variant="secondary" :href="route('employees.show', $employee->id)" class="text-xs">Detail</x-ui.button><x-ui.button :href="route('employees.edit', $employee->id)" class="text-xs">Ubah</x-ui.button></div></div>
                </article>
            @empty
                <div class="p-10 text-center text-sm" style="color: var(--ui-fg-muted)">Data pegawai tidak ditemukan.</div>
            @endforelse
        </div>

        @if($employees->hasPages())
            <x-ui.server-pagination :paginator="$employees" class="mt-5" noun="pegawai" />
        @endif
    </x-ui.form-section>
</div>
