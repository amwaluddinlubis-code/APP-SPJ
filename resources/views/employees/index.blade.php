<x-layouts.tailwind-app title="Pegawai">
    <div class="space-y-6">
        <x-page-header
            title="Pegawai"
            subtitle="Kelola identitas GTK serta keterkaitannya dengan honorarium tahun anggaran aktif."
            kicker="Master Dapodik, ARKAS & Manual"
        >
            <x-slot:actions>
                @if(auth()->user()->isAdministrator())
                    <a href="{{ route('dapodik.index') }}" class="rounded-lg bg-white/15 px-4 py-2 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/25">Sinkron Dapodik</a>
                @endif
                @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))
                    <x-ui.button :href="route('employees.create')"><span class="text-lg leading-none">+</span> Tambah pegawai</x-ui.button>
                @endif
            </x-slot:actions>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Total Data" :value="number_format($summary['total'], 0, ',', '.')" hint="Seluruh data pegawai" />
                <x-stat-item label="Aktif" :value="number_format($summary['active'], 0, ',', '.')" hint="Pegawai berstatus aktif" value-class="text-emerald-700" />
                <x-stat-item label="Dapodik" :value="number_format($summary['dapodik'], 0, ',', '.')" hint="Berasal dari sinkronisasi" value-class="text-indigo-700" />
                <x-stat-item label="Manual" :value="number_format($summary['manual'], 0, ',', '.')" hint="Diinput oleh operator" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-bold text-slate-900">Daftar pegawai</h2><p class="mt-1 text-xs text-slate-500">Data sensitif disamarkan pada tampilan daftar.</p></div>
            <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 p-4 md:grid-cols-12">
                <label class="md:col-span-5"><span class="mb-1 block text-xs font-semibold text-slate-600">Cari pegawai</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, NIP, NIK, NUPTK, atau jabatan" class="w-full rounded-lg border-slate-300 text-sm focus:border-[var(--theme-focus-ring)] focus:ring-[var(--theme-focus-ring)]"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-slate-600">Sumber</span><select name="source" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Semua</option>@foreach(['DAPODIK'=>'Dapodik','MANUAL'=>'Manual','PEGAWAI'=>'ARKAS Pegawai','PTK'=>'ARKAS PTK'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['source'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-slate-600">Status</span><select name="status" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Semua</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak aktif</option></select></label>
                <label class="md:col-span-1"><span class="mb-1 block text-xs font-semibold text-slate-600">Baris</span><select name="perPage" class="w-full rounded-lg border-slate-300 text-sm">@foreach ([15,25,50,100] as $size)<option value="{{ $size }}" @selected($employees->perPage() === $size)>{{ $size }}</option>@endforeach</select></label>
                <div class="flex items-end gap-2 md:col-span-2"><x-ui.button type="submit">Terapkan</x-ui.button><x-ui.button variant="secondary" :href="route('employees.index')">Reset</x-ui.button></div>
            </form>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Pegawai</th><th class="px-4 py-3">Identitas</th><th class="px-4 py-3">Kepegawaian</th><th class="px-4 py-3 text-right">Honor tahun aktif</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                    @forelse ($employees as $employee)
                        <tr class="odd:bg-white even:bg-slate-50 hover:bg-[var(--theme-accent-soft)]">
                            <td class="px-4 py-3"><div class="font-semibold text-slate-900">{{ $employee->name }}</div><div class="mt-1"><span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $employee->source_type }}</span><span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $employee->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $employee->is_active ? 'Aktif' : 'Tidak aktif' }}</span></div></td>
                            <td class="px-4 py-3 text-slate-600"><div>NIP: {{ $employee->nip ?: '—' }}</div><div>NUPTK: {{ $employee->nuptk ?: '—' }}</div><div>NIK: {{ $employee->nik ? '••••'.substr($employee->nik, -4) : '—' }}</div></td>
                            <td class="px-4 py-3"><div class="font-medium text-slate-800">{{ $employee->position ?: 'Belum tercatat' }}</div><div class="text-xs text-slate-500">{{ collect([$employee->staff_type, $employee->employment_status])->filter()->join(' · ') ?: '—' }}</div></td>
                            <td class="px-4 py-3 text-right"><div class="font-semibold text-slate-900">Rp {{ number_format($employee->honor_net, 0, ',', '.') }}</div><div class="text-xs text-slate-500">{{ $employee->honor_count }} rincian · bruto Rp {{ number_format($employee->honor_gross, 0, ',', '.') }}</div></td>
                            <td class="px-4 py-3 text-right"><x-ui.button variant="secondary" :href="route('employees.show', $employee->id)" class="text-xs">Lihat detail</x-ui.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold text-slate-700">Data pegawai tidak ditemukan.</p><p class="mt-1 text-sm text-slate-500">Sinkronkan ARKAS atau ubah kriteria pencarian.</p></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-200 md:hidden">@forelse($employees as $employee)<article class="p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-slate-900">{{ $employee->name }}</h3><p class="mt-1 text-xs text-slate-500">NUPTK {{ $employee->nuptk?:'—' }} · {{ $employee->position?:'Jabatan belum tercatat' }}</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ $employee->is_active?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $employee->is_active?'Aktif':'Nonaktif' }}</span></div><div class="mt-3 flex items-center justify-between"><span class="text-xs font-semibold text-slate-500">{{ $employee->source_type }}</span><a href="{{ route('employees.show',$employee) }}" class="rounded-lg theme-bg-soft px-3 py-2 text-xs font-bold theme-text">Lihat detail</a></div></article>@empty<div class="p-10 text-center text-sm text-slate-500">Data pegawai tidak ditemukan.</div>@endforelse</div>
            @if ($employees->hasPages())<div class="border-t border-slate-200 p-4" data-pagination="server">{{ $employees->links() }}</div>@endif
        </section>
    </div>
</x-layouts.tailwind-app>
