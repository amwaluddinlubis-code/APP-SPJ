<x-layouts.tailwind-app title="{{ $student->exists ? 'Ubah Siswa' : 'Tambah Siswa' }}">
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header
            :title="$student->exists ? 'Ubah Siswa' : 'Tambah Siswa Manual'"
            subtitle="Lengkapi data inti siswa terlebih dahulu. Informasi tambahan dapat diisi sesuai kebutuhan administrasi sekolah."
            kicker="Data Peserta Didik"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Mode Data" value="Manual" hint="Diisi oleh operator" value-class="text-indigo-700" />
                <x-stat-item label="Pemadanan" value="NISN → ID Dapodik" hint="Saat sinkronisasi Dapodik" value-class="text-slate-800" />
                <x-stat-item label="Status" :value="$student->exists ? ($student->is_active ? 'Aktif' : 'Tidak aktif') : 'Siswa baru'" :hint="$student->exists ? 'Status data saat ini' : 'Akan dibuat sebagai data manual'" :value-class="$student->exists && !$student->is_active ? 'text-rose-700' : 'text-emerald-700'" />
            </div>
        </x-page-header>

        <form method="POST" action="{{ $student->exists ? route('students.update',$student) : route('students.store') }}" class="space-y-5">
            @csrf
            @if($student->exists)@method('PUT')@endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    <p class="font-bold">Periksa kembali data yang belum sesuai.</p>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <x-ui.form-section title="Identitas utama" description="Data dasar yang paling sering dipakai pada administrasi dan pemadanan Dapodik.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Nama lengkap" for="student-name" :required="true" :error="$errors->first('name')" class="md:col-span-2">
                        <x-ui.input id="student-name" name="name" :value="old('name',$student->name)" required autocomplete="name" placeholder="Nama lengkap sesuai dokumen resmi" />
                    </x-ui.field>

                    <x-ui.field label="NISN" for="student-nisn" hint="Gunakan NISN resmi bila tersedia." :error="$errors->first('nisn')">
                        <x-ui.input id="student-nisn" name="nisn" :value="old('nisn',$student->nisn)" inputmode="numeric" placeholder="Contoh: 0123456789" />
                    </x-ui.field>

                    <x-ui.field label="NIPD" for="student-nipd" hint="Nomor induk peserta didik di sekolah." :error="$errors->first('nipd')">
                        <x-ui.input id="student-nipd" name="nipd" :value="old('nipd',$student->nipd)" />
                    </x-ui.field>

                    <x-ui.field label="NIK" for="student-nik" :error="$errors->first('nik')">
                        <x-ui.input id="student-nik" name="nik" :value="old('nik',$student->nik)" inputmode="numeric" />
                    </x-ui.field>

                    <x-ui.field label="Jenis kelamin" for="student-gender" :error="$errors->first('gender')">
                        <x-ui.select id="student-gender" name="gender">
                            <option value="">Pilih jenis kelamin</option>
                            <option value="L" @selected(old('gender',$student->gender)==='L')>Laki-laki</option>
                            <option value="P" @selected(old('gender',$student->gender)==='P')>Perempuan</option>
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Tempat lahir" for="student-birth-place" :error="$errors->first('birth_place')">
                        <x-ui.input id="student-birth-place" name="birth_place" :value="old('birth_place',$student->birth_place)" />
                    </x-ui.field>

                    <x-ui.field label="Tanggal lahir" for="student-birth-date" :error="$errors->first('birth_date')">
                        <x-ui.input id="student-birth-date" type="date" name="birth_date" :value="old('birth_date',$student->birth_date?->format('Y-m-d'))" />
                    </x-ui.field>

                    <x-ui.field label="Agama" for="student-religion" :error="$errors->first('religion')">
                        <x-ui.input id="student-religion" name="religion" :value="old('religion',$student->religion)" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Sekolah & pendaftaran" description="Informasi rombongan belajar dan riwayat masuk siswa.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Rombel / kelas" for="student-class" :error="$errors->first('class_name')">
                        <x-ui.input id="student-class" name="class_name" :value="old('class_name',$student->class_name)" placeholder="Contoh: IV-A" />
                    </x-ui.field>

                    <x-ui.field label="Tingkat" for="student-grade" hint="Contoh: 1, 2, 3, 4, 5, atau 6." :error="$errors->first('grade_level')">
                        <x-ui.input id="student-grade" name="grade_level" :value="old('grade_level',$student->grade_level)" />
                    </x-ui.field>

                    <x-ui.field label="Jenis pendaftaran" for="student-registration-type" :error="$errors->first('registration_type')">
                        <x-ui.input id="student-registration-type" name="registration_type" :value="old('registration_type',$student->registration_type)" placeholder="Contoh: Siswa baru / pindahan" />
                    </x-ui.field>

                    <x-ui.field label="Tanggal masuk" for="student-entry-date" :error="$errors->first('school_entry_date')">
                        <x-ui.input id="student-entry-date" type="date" name="school_entry_date" :value="old('school_entry_date',$student->school_entry_date?->format('Y-m-d'))" />
                    </x-ui.field>

                    <x-ui.field label="Sekolah asal" for="student-previous-school" :error="$errors->first('previous_school')" class="md:col-span-2">
                        <x-ui.input id="student-previous-school" name="previous_school" :value="old('previous_school',$student->previous_school)" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Kontak & keluarga" description="Data komunikasi serta orang tua/wali. Isi yang tersedia dan relevan saja.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Alamat" for="student-address" :error="$errors->first('address')" class="md:col-span-2">
                        <x-ui.textarea id="student-address" name="address" rows="3" placeholder="Alamat tempat tinggal siswa">{{ old('address',$student->address) }}</x-ui.textarea>
                    </x-ui.field>

                    <x-ui.field label="Telepon" for="student-phone" :error="$errors->first('phone')">
                        <x-ui.input id="student-phone" name="phone" :value="old('phone',$student->phone)" inputmode="tel" />
                    </x-ui.field>

                    <x-ui.field label="Email" for="student-email" :error="$errors->first('email')">
                        <x-ui.input id="student-email" type="email" name="email" :value="old('email',$student->email)" autocomplete="email" />
                    </x-ui.field>

                    <x-ui.field label="Nama ayah" for="student-father" :error="$errors->first('father_name')">
                        <x-ui.input id="student-father" name="father_name" :value="old('father_name',$student->father_name)" />
                    </x-ui.field>

                    <x-ui.field label="Nama ibu" for="student-mother" :error="$errors->first('mother_name')">
                        <x-ui.input id="student-mother" name="mother_name" :value="old('mother_name',$student->mother_name)" />
                    </x-ui.field>

                    <x-ui.field label="Nama wali" for="student-guardian" hint="Isi bila siswa menggunakan wali." :error="$errors->first('guardian_name')" class="md:col-span-2">
                        <x-ui.input id="student-guardian" name="guardian_name" :value="old('guardian_name',$student->guardian_name)" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>

            <x-ui.form-section title="Informasi tambahan" description="Data opsional untuk kebutuhan administrasi dan pendataan sekolah.">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-ui.field label="Anak ke" for="student-child-order" :error="$errors->first('child_order')">
                        <x-ui.input id="student-child-order" type="number" name="child_order" :value="old('child_order',$student->child_order)" min="1" />
                    </x-ui.field>

                    <x-ui.field label="Tinggi badan" for="student-height" hint="Dalam sentimeter." :error="$errors->first('height')">
                        <x-ui.input id="student-height" type="number" name="height" :value="old('height',$student->height)" min="0" />
                    </x-ui.field>

                    <x-ui.field label="Berat badan" for="student-weight" hint="Dalam kilogram." :error="$errors->first('weight')">
                        <x-ui.input id="student-weight" type="number" name="weight" :value="old('weight',$student->weight)" min="0" step="0.1" />
                    </x-ui.field>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-slate-50">
                        <input type="checkbox" name="special_needs" value="1" @checked(old('special_needs',$student->special_needs)) class="mt-0.5 rounded border-slate-300 text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]">
                        <span><span class="block text-sm font-semibold text-slate-800">Berkebutuhan khusus</span><span class="mt-1 block text-xs text-slate-500">Aktifkan bila informasi ini memang tercatat pada administrasi sekolah.</span></span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-slate-50">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active',$student->exists ? $student->is_active : true)) class="mt-0.5 rounded border-slate-300 text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]">
                        <span><span class="block text-sm font-semibold text-slate-800">Siswa aktif</span><span class="mt-1 block text-xs text-slate-500">Nonaktifkan bila siswa sudah tidak menjadi peserta didik aktif.</span></span>
                    </label>
                </div>
            </x-ui.form-section>

            <div class="sticky bottom-4 z-10 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Pastikan nama dan identitas utama sudah benar sebelum menyimpan.</p>
                <div class="flex gap-2 sm:justify-end">
                    <x-ui.button variant="secondary" :href="$student->exists ? route('students.show',$student) : route('students.index')">Batal</x-ui.button>
                    <x-ui.button type="submit">{{ $student->exists ? 'Simpan perubahan' : 'Simpan siswa' }}</x-ui.button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.tailwind-app>
