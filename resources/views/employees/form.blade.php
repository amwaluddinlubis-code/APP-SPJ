<x-layouts.tailwind-app title="{{ $employee->exists ? 'Ubah Pegawai' : 'Tambah Pegawai' }}">
    <div class="mx-auto max-w-6xl space-y-6">
        <x-page-header
            :title="$employee->exists ? 'Ubah Pegawai' : 'Tambah Pegawai Manual'"
            subtitle="Isi data seperlunya. Identitas utama digunakan untuk membantu pemadanan saat sinkronisasi Dapodik."
            kicker="Data Pegawai"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Mode Data" value="Manual" hint="Diisi oleh operator" value-class="text-indigo-700" />
                <x-stat-item label="Pemadanan" value="NUPTK → Nama" hint="Saat sinkronisasi Dapodik" value-class="text-slate-800" />
                <x-stat-item label="Status" :value="$employee->exists ? ($employee->is_active ? 'Aktif' : 'Tidak aktif') : 'Pegawai baru'" :hint="$employee->exists ? 'Status data saat ini' : 'Akan dibuat sebagai data manual'" :value-class="$employee->exists && !$employee->is_active ? 'text-rose-700' : 'text-emerald-700'" />
            </div>
        </x-page-header>

        <form method="POST" action="{{ $employee->exists ? route('employees.update',$employee) : route('employees.store') }}" class="space-y-6">
            @csrf
            @if($employee->exists)@method('PUT')@endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-bold">Ada data yang perlu diperiksa kembali.</p>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <x-ui.form-section title="Identitas utama" description="Utamakan nama lengkap dan NUPTK karena digunakan untuk membantu pemadanan data.">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="Nama lengkap" for="name" required :error="$errors->first('name')">
                        <x-ui.input id="name" name="name" :value="old('name',$employee->name)" autocomplete="name" required />
                    </x-ui.field>
                    <x-ui.field label="NUPTK" for="nuptk" hint="Isi jika tersedia agar pemadanan lebih akurat." :error="$errors->first('nuptk')">
                        <x-ui.input id="nuptk" name="nuptk" :value="old('nuptk',$employee->nuptk)" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="NIP" for="nip" :error="$errors->first('nip')">
                        <x-ui.input id="nip" name="nip" :value="old('nip',$employee->nip)" />
                    </x-ui.field>
                    <x-ui.field label="NIK" for="nik" hint="Data identitas sensitif; gunakan hanya untuk kebutuhan administrasi." :error="$errors->first('nik')">
                        <x-ui.input id="nik" name="nik" :value="old('nik',$employee->nik)" inputmode="numeric" />
                    </x-ui.field>
                    <x-ui.field label="Tempat lahir" for="birth_place" :error="$errors->first('birth_place')">
                        <x-ui.input id="birth_place" name="birth_place" :value="old('birth_place',$employee->birth_place)" />
                    </x-ui.field>
                    <x-ui.field label="Tanggal lahir" for="birth_date" :error="$errors->first('birth_date')">
                        <x-ui.input id="birth_date" type="date" name="birth_date" :value="old('birth_date',$employee->birth_date?->format('Y-m-d'))" />
                    </x-ui.field>
                    <x-ui.field label="Jenis kelamin" for="gender" :error="$errors->first('gender')">
                        <x-ui.select id="gender" name="gender">
                            <option value="">Pilih jenis kelamin</option>
                            <option value="L" @selected(old('gender',$employee->gender)==='L')>Laki-laki</option>
                            <option value="P" @selected(old('gender',$employee->gender)==='P')>Perempuan</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Agama" for="religion" :error="$errors->first('religion')">
                        <x-ui.input id="religion" name="religion" :value="old('religion',$employee->religion)" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Kepegawaian" description="Informasi jabatan dan status digunakan pada dokumen honorarium serta pelaporan.">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="Status kepegawaian" for="employment_status" :error="$errors->first('employment_status')">
                        <x-ui.input id="employment_status" name="employment_status" :value="old('employment_status',$employee->employment_status)" placeholder="Contoh: ASN, Non-ASN" />
                    </x-ui.field>
                    <x-ui.field label="Jenis PTK" for="staff_type" :error="$errors->first('staff_type')">
                        <x-ui.input id="staff_type" name="staff_type" :value="old('staff_type',$employee->staff_type)" />
                    </x-ui.field>
                    <x-ui.field label="Jabatan" for="position" :error="$errors->first('position')">
                        <x-ui.input id="position" name="position" :value="old('position',$employee->position)" />
                    </x-ui.field>
                    <x-ui.field label="Pangkat / Golongan" for="rank_group" :error="$errors->first('rank_group')">
                        <x-ui.input id="rank_group" name="rank_group" :value="old('rank_group',$employee->rank_group)" />
                    </x-ui.field>
                    <x-ui.field label="Pendidikan terakhir" for="last_education" :error="$errors->first('last_education')">
                        <x-ui.input id="last_education" name="last_education" :value="old('last_education',$employee->last_education)" />
                    </x-ui.field>
                    <x-ui.field label="Bidang studi terakhir" for="last_study_field" :error="$errors->first('last_study_field')">
                        <x-ui.input id="last_study_field" name="last_study_field" :value="old('last_study_field',$employee->last_study_field)" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Pajak & pembayaran" description="Data ini dipakai bila pegawai menjadi penerima honorarium atau pembayaran terkait.">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="NPWP" for="npwp" :error="$errors->first('npwp')">
                        <x-ui.input id="npwp" name="npwp" :value="old('npwp',$employee->npwp)" />
                    </x-ui.field>
                    <x-ui.field label="Bank" for="bank_name" :error="$errors->first('bank_name')">
                        <x-ui.input id="bank_name" name="bank_name" :value="old('bank_name',$employee->bank_name)" />
                    </x-ui.field>
                    <x-ui.field label="Nomor rekening" for="bank_account" :error="$errors->first('bank_account')">
                        <x-ui.input id="bank_account" name="bank_account" :value="old('bank_account',$employee->bank_account)" inputmode="numeric" />
                    </x-ui.field>
                </div>

                <label class="mt-5 flex max-w-xl items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active',$employee->exists ? $employee->is_active : true)) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]">
                    <span><span class="block text-sm font-semibold text-slate-800">Pegawai aktif</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">Nonaktifkan jika pegawai tidak lagi digunakan pada transaksi baru. Riwayat lama tetap dipertahankan.</span></span>
                </label>
            </x-ui.form-section>

            <div class="sticky bottom-4 z-20 flex flex-col-reverse gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-end">
                <x-ui.button variant="secondary" :href="$employee->exists ? route('employees.show',$employee) : route('employees.index')">Batal</x-ui.button>
                <x-ui.button type="submit">{{ $employee->exists ? 'Simpan perubahan' : 'Simpan pegawai' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.tailwind-app>
