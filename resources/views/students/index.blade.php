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

        <x-ui.form-section
            title="Daftar siswa"
            description="Gunakan pencarian dan filter seperlunya. Data Dapodik tetap dapat dibedakan dari data manual."
            class="overflow-hidden"
        >
            <form method="GET" class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 lg:grid-cols-12">
                <x-ui.field label="Pencarian" for="student-search" hint="Nama, NISN, NIPD, atau kelas." class="lg:col-span-5">
                    <x-ui.input id="student-search" name="q" :value="$filters['q'] ?? ''" placeholder="Contoh: Ahmad atau 1234567890" />
                </x-ui.field>

                <x-ui.field label="Rombel" for="student-class" class="lg:col-span-2">
                    <x-ui.select id="student-class" name="class">
                        <option value="">Semua kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class }}" @selected(($filters['class'] ?? '') === $class)>{{ $class }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Sumber data" for="student-source" class="lg:col-span-2">
                    <x-ui.select id="student-source" name="source">
                        <option value="">Semua sumber</option>
                        <option value="DAPODIK" @selected(($filters['source'] ?? '') === 'DAPODIK')>Dapodik</option>
                        <option value="MANUAL" @selected(($filters['source'] ?? '') === 'MANUAL')>Manual</option>
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field label="Baris" for="student-per-page" class="lg:col-span-1">
                    <x-ui.select id="student-per-page" name="perPage">
                        @foreach([15,25,50,100] as $size)
                            <option value="{{ $size }}" @selected($students->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <div class="flex items-end gap-2 lg:col-span-2">
                    <x-ui.button type="submit" class="flex-1">Terapkan</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('students.index')">Reset</x-ui.button>
                </div>
            </form>

            <div class="mt-5 hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr><th class="px-5 py-3">Siswa</th><th class="px-5 py-3">Identitas</th><th class="px-5 py-3">Rombel</th><th class="px-5 py-3">Orang tua/Wali</th><th class="px-5 py-3 text-right">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($students as $student)
                            <tr class="bg-white transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">{{ $student->name }}</div>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->source_type==='DAPODIK'?'bg-blue-100 text-blue-700':'bg-amber-100 text-amber-700' }}">{{ $student->source_type }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $student->is_active?'Aktif':'Tidak aktif' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><div>NISN: <span class="font-medium text-slate-800">{{ $student->nisn?:'—' }}</span></div><div class="mt-1 text-xs">NIPD: {{ $student->nipd?:'—' }}</div></td>
                                <td class="px-5 py-4"><div class="font-medium text-slate-800">{{ $student->class_name?:'Belum ditempatkan' }}</div><div class="mt-1 text-xs text-slate-500">Tingkat {{ $student->grade_level?:'—' }}</div></td>
                                <td class="px-5 py-4 text-slate-700">{{ $student->father_name?:$student->mother_name?:$student->guardian_name?:'—' }}</td>
                                <td class="px-5 py-4 text-right"><x-ui.button variant="secondary" :href="route('students.show',$student)" class="text-xs">Lihat detail</x-ui.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold text-slate-700">Data siswa tidak ditemukan.</p><p class="mt-1 text-sm text-slate-500">Sinkronkan Dapodik atau ubah kriteria pencarian.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white md:hidden">
                @forelse($students as $student)
                    <article class="border-b border-slate-100 p-4 last:border-b-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="truncate font-bold text-slate-900">{{ $student->name }}</h3><p class="mt-1 text-xs text-slate-500">NISN {{ $student->nisn?:'—' }} · {{ $student->class_name?:'Tanpa rombel' }}</p></div>
                            <span class="rounded-full px-2 py-1 text-xs font-bold {{ $student->is_active?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $student->is_active?'Aktif':'Nonaktif' }}</span>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3"><span class="text-xs font-semibold text-slate-500">{{ $student->source_type }}</span><x-ui.button variant="secondary" :href="route('students.show',$student)" class="text-xs">Lihat detail</x-ui.button></div>
                    </article>
                @empty
                    <div class="p-10 text-center text-sm text-slate-500">Data siswa tidak ditemukan.</div>
                @endforelse
            </div>

            @if($students->hasPages())<div class="mt-5 border-t border-slate-200 pt-4" data-pagination="server">{{ $students->links() }}</div>@endif
        </x-ui.form-section>
    </div>
</x-layouts.tailwind-app>
