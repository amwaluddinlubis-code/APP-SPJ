<x-layouts.tailwind-app title="Siswa">
    <div class="space-y-6">
        <x-page-header
            title="Siswa"
            subtitle="Kelola identitas peserta didik, rombongan belajar, dan data orang tua dalam satu sumber terstruktur."
            kicker="Master Dapodik & Manual"
        >
            <x-slot:actions>
                @if(auth()->user()->isAdministrator())
                    <a href="{{ route('dapodik.index') }}" class="rounded-lg bg-white/15 px-4 py-2 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/25">Sinkron Dapodik</a>
                @endif
                @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))
                    <a href="{{ route('students.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-bold text-[var(--theme-700)] shadow"><span class="text-lg leading-none">+</span> Tambah siswa</a>
                @endif
            </x-slot:actions>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Total Data" :value="number_format($summary['total'], 0, ',', '.')" hint="Seluruh data siswa" />
                <x-stat-item label="Siswa Aktif" :value="number_format($summary['active'], 0, ',', '.')" hint="Berstatus aktif" value-class="text-emerald-700" />
                <x-stat-item label="Dari Dapodik" :value="number_format($summary['dapodik'], 0, ',', '.')" hint="Berasal dari sinkronisasi" value-class="text-indigo-700" />
                <x-stat-item label="Data Manual" :value="number_format($summary['manual'], 0, ',', '.')" hint="Diinput oleh operator" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-bold text-slate-900">Daftar siswa</h2><p class="mt-1 text-xs text-slate-500">Gunakan pencarian dan filter untuk menemukan data secara cepat.</p></div>
            <form method="GET" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 p-4 md:grid-cols-12">
                <label class="md:col-span-5"><span class="mb-1 block text-xs font-semibold text-slate-600">Pencarian</span><input name="q" value="{{ $filters['q']??'' }}" placeholder="Nama, NISN, NIPD, atau kelas" class="w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-slate-600">Rombel</span><select name="class" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Semua kelas</option>@foreach($classes as $class)<option value="{{ $class }}" @selected(($filters['class']??'')===$class)>{{ $class }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-slate-600">Sumber</span><select name="source" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Semua sumber</option><option value="DAPODIK" @selected(($filters['source']??'')==='DAPODIK')>Dapodik</option><option value="MANUAL" @selected(($filters['source']??'')==='MANUAL')>Manual</option></select></label>
                <label class="md:col-span-1"><span class="mb-1 block text-xs font-semibold text-slate-600">Baris</span><select name="perPage" class="w-full rounded-lg border-slate-300 text-sm">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected($students->perPage()===$size)>{{ $size }}</option>@endforeach</select></label>
                <div class="flex items-end gap-2 md:col-span-2"><button class="rounded-lg bg-[var(--theme-600)] px-4 py-2 text-sm font-bold text-white">Terapkan</button><a href="{{ route('students.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600">Reset</a></div>
            </form>
            <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600"><tr><th class="px-5 py-3">Siswa</th><th class="px-5 py-3">Identitas</th><th class="px-5 py-3">Rombel</th><th class="px-5 py-3">Orang tua/Wali</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-200">
                @forelse($students as $student)<tr class="odd:bg-white even:bg-slate-50 hover:bg-[var(--theme-50)]"><td class="px-5 py-3"><div class="font-semibold text-slate-900">{{ $student->name }}</div><div class="mt-1 flex gap-1"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->source_type==='DAPODIK'?'bg-blue-100 text-blue-700':'bg-amber-100 text-amber-700' }}">{{ $student->source_type }}</span><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $student->is_active?'Aktif':'Tidak aktif' }}</span></div></td><td class="px-5 py-3 text-slate-600"><div>NISN: <span class="font-medium text-slate-800">{{ $student->nisn?:'—' }}</span></div><div>NIPD: {{ $student->nipd?:'—' }}</div></td><td class="px-5 py-3"><div class="font-medium">{{ $student->class_name?:'Belum ditempatkan' }}</div><div class="text-xs text-slate-500">Tingkat {{ $student->grade_level?:'—' }}</div></td><td class="px-5 py-3"><div>{{ $student->father_name?:$student->mother_name?:$student->guardian_name?:'—' }}</div></td><td class="px-5 py-3 text-right"><a href="{{ route('students.show',$student) }}" class="inline-flex items-center gap-1 rounded-lg border border-[var(--theme-300)] px-3 py-2 text-xs font-bold text-[var(--theme-700)]"><x-ui-icon name="edit" class="h-4 w-4" /> Detail</a></td></tr>
                @empty<tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold text-slate-700">Data siswa tidak ditemukan.</p><p class="mt-1 text-sm text-slate-500">Sinkronkan Dapodik atau ubah kriteria pencarian.</p></td></tr>@endforelse
            </tbody></table></div>
            <div class="divide-y divide-slate-200 md:hidden">@forelse($students as $student)<article class="p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-slate-900">{{ $student->name }}</h3><p class="mt-1 text-xs text-slate-500">NISN {{ $student->nisn?:'—' }} · {{ $student->class_name?:'Tanpa rombel' }}</p></div><span class="rounded-full px-2 py-1 text-xs font-bold {{ $student->is_active?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $student->is_active?'Aktif':'Nonaktif' }}</span></div><div class="mt-3 flex items-center justify-between"><span class="text-xs font-semibold text-slate-500">{{ $student->source_type }}</span><a href="{{ route('students.show',$student) }}" class="rounded-lg theme-bg-soft px-3 py-2 text-xs font-bold theme-text">Lihat detail</a></div></article>@empty<div class="p-10 text-center text-sm text-slate-500">Data siswa tidak ditemukan.</div>@endforelse</div>
            @if($students->hasPages())<div class="border-t border-slate-200 p-4" data-pagination="server">{{ $students->links() }}</div>@endif
        </section>
    </div>
</x-layouts.tailwind-app>
