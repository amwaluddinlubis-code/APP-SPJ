<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Profil Sekolah dan Tahun Anggaran"
            subtitle="Identitas dipakai pada dokumen, template, kop surat, dan database lokal setiap sekolah."
            kicker="Pengaturan Identitas"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Sekolah Aktif" :value="$activeSchool?->name ?? 'Belum dipilih'" :hint="$activeSchool ? 'NPSN '.$activeSchool->npsn : 'Pilih sekolah aktif'" value-class="text-indigo-700" />
                <x-stat-item label="Tahun Aktif" :value="$activeYear?->year ?? '—'" :hint="$activeYear?->fund_source ?? 'Belum ada konteks tahun'" value-class="text-emerald-700" />
                <x-stat-item label="Total Sekolah" :value="number_format($schools->count(), 0, ',', '.')" hint="Database sekolah terdaftar" />
                <x-stat-item label="Kode Sekolah" :value="$activeSchool?->school_code ?: '—'" hint="Dipakai pada penomoran dokumen" value-class="text-slate-800" />
            </div>
        </x-page-header>

        @if ($activeSchool)
            <form method="POST" action="{{ route('schools.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/70 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="font-bold text-indigo-950">Profil Sekolah Aktif</h2><p class="mt-1 text-sm text-indigo-700">{{ $activeSchool->name }} · {{ $activeYear ? 'Tahun '.$activeYear->year.' / '.$activeYear->fund_source : 'Pilih tahun untuk mengatur penandatangan per tahun' }}</p></div>
                        <div class="flex flex-wrap gap-2"><span class="rounded-full bg-white px-3 py-1.5 text-sm font-bold text-indigo-700">Kode {{ $activeSchool->school_code ?: 'Belum diisi' }}</span><span class="rounded-full bg-white px-3 py-1.5 text-sm font-bold text-indigo-700">NPSN {{ $activeSchool->npsn }}</span></div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
                    <x-ui.form-section title="Identitas dan Alamat" description="Data dasar sekolah yang akan dipakai pada dokumen dan penomoran.">
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Kode Sekolah" for="school-code" hint="Dipakai oleh placeholder {SCHOOL}." :error="$errors->first('school_code')" required><x-ui.input id="school-code" name="school_code" :value="old('school_code', $activeSchool->school_code)" maxlength="40" pattern="[A-Za-z0-9._-]+" required /></x-ui.field>
                            <x-ui.field label="NPSN" for="school-npsn" hint="Dipakai oleh placeholder {NPSN}." :error="$errors->first('npsn')" required><x-ui.input id="school-npsn" name="npsn" :value="old('npsn', $activeSchool->npsn)" required /></x-ui.field>
                            <x-ui.field label="Nama Sekolah" for="school-name" :error="$errors->first('name')" required class="md:col-span-2"><x-ui.input id="school-name" name="name" :value="old('name', $activeSchool->name)" required /></x-ui.field>
                            <x-ui.field label="Alamat" for="school-address" class="md:col-span-2"><x-ui.textarea id="school-address" name="address" rows="3">{{ old('address', $activeSchool->address) }}</x-ui.textarea></x-ui.field>
                            <x-ui.field label="Kecamatan" for="school-district"><x-ui.input id="school-district" name="district" :value="old('district', $activeSchool->district)" /></x-ui.field>
                            <x-ui.field label="Kabupaten/Kota" for="school-regency"><x-ui.input id="school-regency" name="regency" :value="old('regency', $activeSchool->regency)" /></x-ui.field>
                            <x-ui.field label="Provinsi" for="school-province" class="md:col-span-2"><x-ui.input id="school-province" name="province" :value="old('province', $activeSchool->province)" /></x-ui.field>
                        </div>
                    </x-ui.form-section>

                    <x-ui.form-section title="Penandatangan Dokumen" description="Nama dan kontak penandatangan mengikuti tahun anggaran aktif.">
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Nama Kepala Sekolah"><x-ui.input name="principal_name" :value="old('principal_name', data_get($profile, 'principal_name', ''))" /></x-ui.field>
                            <x-ui.field label="NIP Kepala Sekolah"><x-ui.input name="principal_nip" :value="old('principal_nip', data_get($profile, 'principal_nip', ''))" /></x-ui.field>
                            <x-ui.field label="Email Kepala Sekolah"><x-ui.input type="email" name="principal_email" :value="old('principal_email', data_get($profile, 'principal_email', ''))" /></x-ui.field>
                            <x-ui.field label="Telepon Kepala Sekolah"><x-ui.input name="principal_phone" :value="old('principal_phone', data_get($profile, 'principal_phone', ''))" /></x-ui.field>
                            <x-ui.field label="Nama Bendahara"><x-ui.input name="treasurer_name" :value="old('treasurer_name', data_get($profile, 'treasurer_name', ''))" /></x-ui.field>
                            <x-ui.field label="NIP Bendahara"><x-ui.input name="treasurer_nip" :value="old('treasurer_nip', data_get($profile, 'treasurer_nip', ''))" /></x-ui.field>
                            <x-ui.field label="Email Bendahara"><x-ui.input type="email" name="treasurer_email" :value="old('treasurer_email', data_get($profile, 'treasurer_email', ''))" /></x-ui.field>
                            <x-ui.field label="Telepon Bendahara"><x-ui.input name="treasurer_phone" :value="old('treasurer_phone', data_get($profile, 'treasurer_phone', ''))" /></x-ui.field>
                        </div>
                    </x-ui.form-section>
                </div>

                <x-ui.form-section title="Kop Surat" description="Unggah PNG/JPG maksimal 5 MB untuk digunakan pada dokumen yang mendukung kop gambar.">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,.7fr)] lg:items-center">
                        <div>
                            <x-ui.field label="Berkas kop surat" for="letterhead"><input id="letterhead" type="file" name="letterhead" accept="image/png,image/jpeg"></x-ui.field>
                        </div>
                        @if ($activeSchool->letterhead_path)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><img src="{{ route('schools.letterhead', ['v' => $activeSchool->updated_at?->timestamp]) }}" alt="Kop surat {{ $activeSchool->name }}" class="h-20 max-w-full rounded bg-white object-contain"><p class="mt-2 text-xs font-semibold text-emerald-700">Kop surat sudah tersimpan</p></div>
                        @endif
                    </div>
                </x-ui.form-section>

                <div class="ui-form-actions"><x-ui.button type="submit">Simpan Profil Aktif</x-ui.button></div>
            </form>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">Pilih sekolah aktif terlebih dahulu agar profil dan penandatangan dapat dikelola.</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.05fr_.95fr] xl:items-start">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Database Sekolah</h2><p class="mt-1 text-sm text-slate-500">Daftar sekolah dan status database operasionalnya.</p></div>
                <div class="divide-y divide-slate-100">@forelse ($schools as $school)<div class="px-5 py-4"><p class="font-bold text-slate-800">{{ $school->name }}</p><p class="mt-1 text-sm text-slate-500">Kode {{ $school->school_code ?: '—' }} · NPSN {{ $school->npsn }} · {{ $school->regency }}</p><span class="mt-2 inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Database {{ $school->databaseRecord?->status ?? 'Belum dibuat' }}</span></div>@empty<p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada sekolah.</p>@endforelse</div>
            </section>

            <div class="space-y-6">
                <x-ui.form-section title="Tambah Sekolah" description="Buat sekolah baru beserta database operasionalnya.">
                    <form method="POST" action="{{ route('schools.store') }}" class="space-y-4">@csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Kode Sekolah" required><x-ui.input name="school_code" maxlength="40" pattern="[A-Za-z0-9._-]+" required /></x-ui.field>
                            <x-ui.field label="NPSN" required><x-ui.input name="npsn" required /></x-ui.field>
                            <x-ui.field label="Nama Sekolah" class="md:col-span-2" required><x-ui.input name="name" required /></x-ui.field>
                            <x-ui.field label="Alamat" class="md:col-span-2"><x-ui.textarea name="address" rows="2"></x-ui.textarea></x-ui.field>
                            <x-ui.field label="Kecamatan"><x-ui.input name="district" /></x-ui.field>
                            <x-ui.field label="Kabupaten/Kota"><x-ui.input name="regency" /></x-ui.field>
                            <x-ui.field label="Provinsi" class="md:col-span-2"><x-ui.input name="province" /></x-ui.field>
                        </div>
                        <div class="flex justify-end"><x-ui.button type="submit">Buat Sekolah dan Database</x-ui.button></div>
                    </form>
                </x-ui.form-section>

                <x-ui.form-section title="Tambah Tahun Anggaran" description="Tambahkan kombinasi tahun dan sumber dana untuk sekolah.">
                    <form method="POST" action="{{ route('years.store') }}" class="space-y-4">@csrf
                        <x-ui.field label="Sekolah" required><x-ui.select name="school_id" required><option value="">Pilih sekolah</option>@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui.field label="Tahun" required><x-ui.input type="number" name="year" :value="date('Y')" min="2020" max="2100" required /></x-ui.field>
                            <x-ui.field label="Sumber Dana" class="md:col-span-2" required><x-ui.select name="fund_source_id" required><option value="">Pilih sumber dana</option>@foreach ($fundSources as $source)<option value="{{ $source->id }}">{{ $source->code }} · {{ $source->name }}</option>@endforeach</x-ui.select></x-ui.field>
                        </div>
                        <div class="flex justify-end"><x-ui.button type="submit" variant="success">Simpan Tahun</x-ui.button></div>
                    </form>
                </x-ui.form-section>
            </div>
        </div>
    </div>
</x-layouts.tailwind-app>
