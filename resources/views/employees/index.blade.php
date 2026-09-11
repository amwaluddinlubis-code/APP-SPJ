<x-layouts.tailwind-app title="Pegawai">
    <div class="space-y-6">
        <x-page-header
            title="Pegawai"
            subtitle="Satu master pegawai untuk data ARKAS, Dapodik, dan input operator. Data hasil sinkronisasi tetap dapat diubah atau dihapus dari aplikasi."
            kicker="Master Pegawai Terpadu"
        >
            <x-slot:actions>
                @if(auth()->user()->isAdministrator())
                    <x-ui.button variant="secondary" :href="route('dapodik.index')">Sinkron Dapodik</x-ui.button>
                @endif
                @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))
                    <x-ui.button :href="route('employees.create')"><x-ui.icon name="plus" size="sm" /> Tambah pegawai</x-ui.button>
                @endif
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-5">
                <x-stat-item label="Total Data" :value="number_format($summary['total'], 0, ',', '.')" hint="Satu row per pegawai" />
                <x-stat-item label="Aktif" :value="number_format($summary['active'], 0, ',', '.')" hint="Pegawai berstatus aktif" />
                <x-stat-item label="ARKAS" :value="number_format($summary['arkas'], 0, ',', '.')" hint="Pernah terlihat di ARKAS" />
                <x-stat-item label="Dapodik" :value="number_format($summary['dapodik'], 0, ',', '.')" hint="Pernah terlihat di Dapodik" />
                <x-stat-item label="Manual" :value="number_format($summary['manual'], 0, ',', '.')" hint="Belum berasal dari feed" />
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border shadow-sm" style="border-color: var(--ui-line); background: var(--ui-surface-base)">
            <x-ui.toolbar class="border-b px-5 py-3" style="border-color: var(--ui-line)">
                <div>
                    <h2 class="font-bold" style="color: var(--ui-fg)">Daftar pegawai</h2>
                    <p class="mt-0.5 text-xs" style="color: var(--ui-fg-muted)">ARKAS dan Dapodik dipadankan berdasarkan NUPTK, NIP, NIK, lalu nama ternormalisasi.</p>
                </div>
                <x-slot:actions>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="source" value="{{ $filters['source'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                        <input type="hidden" name="perPage" value="{{ $employees->perPage() }}">
                        <x-ui.search-group name="q" :value="$filters['q'] ?? ''" placeholder="Cari nama, NIP, NIK, NUPTK..." width="w-72" />
                    </form>
                    <x-page-table-per-page :total="$employees->total()" name="perPage" :current="$employees->perPage()" />
                </x-slot:actions>
            </x-ui.toolbar>

            <form method="GET" class="grid gap-3 border-b p-4 md:grid-cols-[minmax(0,1fr)_14rem_14rem_auto]" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                <input type="hidden" name="perPage" value="{{ $employees->perPage() }}">
                <div></div>
                <x-ui.field label="Sumber">
                    <x-ui.select name="source">
                        <option value="">Semua sumber</option>
                        @foreach(['ARKAS'=>'ARKAS','DAPODIK'=>'Dapodik','MANUAL'=>'Manual'] as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['source'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Status">
                    <x-ui.select name="status">
                        <option value="">Semua status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak aktif</option>
                    </x-ui.select>
                </x-ui.field>
                <div class="flex items-end gap-2">
                    <x-ui.button type="submit" variant="secondary">Terapkan filter</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('employees.index')">Reset</x-ui.button>
                </div>
            </form>

            <div class="hidden md:block">
                <x-ui.table pagination="server">
                    <thead>
                        <tr>
                            <th>Pegawai</th>
                            <th>Identitas</th>
                            <th>Kepegawaian</th>
                            <th class="text-right">Honor tahun aktif</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <div class="font-semibold" style="color: var(--ui-fg-strong)">{{ $employee->name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <x-ui.badge variant="neutral">{{ $employee->source_label }}</x-ui.badge>
                                    @if($employee->operator_locked)
                                        <x-ui.badge variant="warning">Dikoreksi operator</x-ui.badge>
                                    @endif
                                    <x-ui.badge :variant="$employee->is_active ? 'success' : 'danger'">{{ $employee->is_active ? 'Aktif' : 'Tidak aktif' }}</x-ui.badge>
                                </div>
                            </td>
                            <td style="color: var(--ui-fg-muted)">
                                <div>NIP: {{ $employee->nip ?: '—' }}</div>
                                <div>NUPTK: {{ $employee->nuptk ?: '—' }}</div>
                                <div>NIK: {{ $employee->nik ? '••••'.substr($employee->nik, -4) : '—' }}</div>
                            </td>
                            <td>
                                <div class="font-medium" style="color: var(--ui-fg)">{{ $employee->position ?: 'Belum tercatat' }}</div>
                                <div class="text-xs" style="color: var(--ui-fg-muted)">{{ collect([$employee->staff_type, $employee->employment_status])->filter()->join(' · ') ?: '—' }}</div>
                            </td>
                            <td class="text-right">
                                <div class="font-semibold" style="color: var(--ui-fg-strong)">Rp {{ number_format($employee->honor_net, 0, ',', '.') }}</div>
                                <div class="text-xs" style="color: var(--ui-fg-muted)">{{ $employee->honor_count }} rincian · bruto Rp {{ number_format($employee->honor_gross, 0, ',', '.') }}</div>
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    <x-ui.button variant="secondary" :href="route('employees.show', $employee->id)" class="text-xs">Detail</x-ui.button>
                                    <x-ui.button :href="route('employees.edit', $employee->id)" class="text-xs">Ubah</x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <p class="font-semibold" style="color: var(--ui-fg)">Data pegawai tidak ditemukan.</p>
                                <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Sinkronkan ARKAS/Dapodik atau ubah kriteria pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            <div class="divide-y md:hidden" style="border-color: var(--ui-line)">
                @forelse($employees as $employee)
                    <article class="p-4" style="background: var(--ui-surface-base)">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold" style="color: var(--ui-fg-strong)">{{ $employee->name }}</h3>
                                <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">NUPTK {{ $employee->nuptk ?: '—' }} · {{ $employee->position ?: 'Jabatan belum tercatat' }}</p>
                            </div>
                            <x-ui.badge :variant="$employee->is_active ? 'success' : 'danger'">{{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <x-ui.badge variant="neutral">{{ $employee->source_label }}</x-ui.badge>
                            <div class="flex gap-2">
                                <x-ui.button variant="secondary" :href="route('employees.show', $employee)" class="text-xs">Detail</x-ui.button>
                                <x-ui.button :href="route('employees.edit', $employee)" class="text-xs">Ubah</x-ui.button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm" style="color: var(--ui-fg-muted)">Data pegawai tidak ditemukan.</div>
                @endforelse
            </div>

            <x-ui.server-pagination :paginator="$employees" noun="pegawai" />
        </section>
    </div>
</x-layouts.tailwind-app>
