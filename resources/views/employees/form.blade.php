<x-layouts.tailwind-app title="{{ $employee->exists ? 'Ubah Pegawai' : 'Tambah Pegawai' }}">
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header
            :title="$employee->exists ? 'Ubah Pegawai' : 'Tambah Pegawai Manual'"
            subtitle="Pegawai manual dapat dipadankan saat sinkronisasi berdasarkan NUPTK, kemudian nama."
            kicker="Data Pegawai"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Mode Data" value="Manual" hint="Diisi oleh operator" value-class="text-indigo-700" />
                <x-stat-item label="Pemadanan" value="NUPTK → Nama" hint="Saat sinkronisasi Dapodik" value-class="text-slate-800" />
                <x-stat-item label="Status" :value="$employee->exists ? ($employee->is_active ? 'Aktif' : 'Tidak aktif') : 'Pegawai baru'" :hint="$employee->exists ? 'Status data saat ini' : 'Akan dibuat sebagai data manual'" :value-class="$employee->exists && !$employee->is_active ? 'text-rose-700' : 'text-emerald-700'" />
            </div>
        </x-page-header>

        <form method="POST" action="{{ $employee->exists ? route('employees.update',$employee) : route('employees.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @if($employee->exists)@method('PUT')@endif
            @if($errors->any())<div class="mb-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
            @php($fields=[['name','Nama lengkap *','text'],['nuptk','NUPTK','text'],['nip','NIP','text'],['nik','NIK','text'],['birth_place','Tempat lahir','text'],['birth_date','Tanggal lahir','date'],['religion','Agama','text'],['employment_status','Status kepegawaian','text'],['staff_type','Jenis PTK','text'],['position','Jabatan','text'],['last_education','Pendidikan terakhir','text'],['last_study_field','Bidang studi terakhir','text'],['rank_group','Pangkat/Golongan','text'],['npwp','NPWP','text'],['bank_name','Bank','text'],['bank_account','Nomor rekening','text']])
            <div class="grid gap-4 md:grid-cols-2">@foreach($fields as [$name,$label,$type])<label><span class="mb-1 block text-sm font-semibold text-slate-700">{{ $label }}</span><input type="{{ $type }}" name="{{ $name }}" value="{{ old($name,$type==='date' ? $employee->$name?->format('Y-m-d') : $employee->$name) }}" @required($name==='name') class="w-full rounded-lg border-slate-300"></label>@endforeach
            <label><span class="mb-1 block text-sm font-semibold">Jenis kelamin</span><select name="gender" class="w-full rounded-lg border-slate-300"><option value="">—</option><option value="L" @selected(old('gender',$employee->gender)==='L')>Laki-laki</option><option value="P" @selected(old('gender',$employee->gender)==='P')>Perempuan</option></select></label><label class="flex items-center gap-2 pt-7"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$employee->exists ? $employee->is_active : true)) class="rounded border-slate-300"><span class="font-semibold">Pegawai aktif</span></label></div>
            <div class="mt-6 flex justify-end gap-2"><a href="{{ $employee->exists ? route('employees.show',$employee) : route('employees.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Batal</a><button class="rounded-lg bg-[var(--theme-600)] px-5 py-2 font-bold text-white">Simpan</button></div>
        </form>
    </div>
</x-layouts.tailwind-app>
