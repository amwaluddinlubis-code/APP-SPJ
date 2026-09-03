<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Profil Sekolah dan Tahun Anggaran"
            subtitle="Atur identitas sekolah, penandatangan, kop surat, dan tahun anggaran yang digunakan pada dokumen SPJ."
            kicker="PENGATURAN SEKOLAH"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Sekolah Aktif" :value="$activeSchool?->name ?? 'Belum dipilih'" :hint="$activeSchool ? 'NPSN '.$activeSchool->npsn : 'Pilih sekolah aktif terlebih dahulu'" value-class="text-indigo-700" />
                <x-stat-item label="Tahun Anggaran Aktif" :value="$activeYear?->year ?? '—'" :hint="$activeYear?->fund_source ?? 'Tahun anggaran belum dipilih'" value-class="text-emerald-700" />
                <x-stat-item label="Jumlah Sekolah" :value="number_format($schools->count(), 0, ',', '.')" hint="Sekolah yang sudah terdaftar" />
                <x-stat-item label="Kode Sekolah" :value="$activeSchool?->school_code ?: '—'" hint="Digunakan pada nomor dokumen" value-class="text-slate-800" />
            </div>
        </x-page-header>

        @if ($activeSchool)
            <form method="POST" action="{{ route('schools.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/70 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h2 class="font-bold text-indigo-950">Sekolah yang Sedang Diatur</h2><p class="mt-1 text-sm text-indigo-700">{{ $activeSchool->name }} · {{ $activeYear ? 'Tahun Anggaran '.$activeYear->year.' / '.$activeYear->fund_source : 'Pilih tahun anggaran untuk mengatur penandatangan' }}</p></div>
                        <div class="flex flex-wrap gap-2"><span class="rounded-full bg-white px-3 py-1.5 text-sm font-bold text-indigo-700">Kode: {{ $activeSchool->school_code ?: 'Belum diisi' }}</span><span class="rounded-full bg-white px-3 py-1.5 text-sm font-bold text-indigo-700">NPSN: {{ $activeSchool->npsn }}</span></div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
                    <x-ui.form-section title="Identitas dan Alamat Sekolah" description="Data ini akan digunakan pada dokumen SPJ dan penomoran dokumen.">
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Kode Sekolah" for="school-code" hint="Digunakan oleh penanda {SCHOOL} pada format nomor dokumen." :error="$errors->first('school_code')" required><x-ui.input id="school-code" name="school_code" :value="old('school_code', $activeSchool->school_code)" maxlength="40" pattern="[A-Za-z0-9._-]+" required /></x-ui.field>
                            <x-ui.field label="NPSN" for="school-npsn" hint="Digunakan oleh penanda {NPSN}." :error="$errors->first('npsn')" required><x-ui.input id="school-npsn" name="npsn" :value="old('npsn', $activeSchool->npsn)" required /></x-ui.field>
                            <x-ui.field label="Nama Sekolah" for="school-name" :error="$errors->first('name')" required class="md:col-span-2"><x-ui.input id="school-name" name="name" :value="old('name', $activeSchool->name)" required /></x-ui.field>
                            <x-ui.field label="Alamat Sekolah" for="school-address" class="md:col-span-2"><x-ui.textarea id="school-address" name="address" rows="3">{{ old('address', $activeSchool->address) }}</x-ui.textarea></x-ui.field>
                            <x-ui.field label="Kecamatan" for="school-district"><x-ui.input id="school-district" name="district" :value="old('district', $activeSchool->district)" /></x-ui.field>
                            <x-ui.field label="Kabupaten/Kota" for="school-regency"><x-ui.input id="school-regency" name="regency" :value="old('regency', $activeSchool->regency)" /></x-ui.field>
                            <x-ui.field label="Provinsi" for="school-province" class="md:col-span-2"><x-ui.input id="school-province" name="province" :value="old('province', $activeSchool->province)" /></x-ui.field>
                        </div>
                    </x-ui.form-section>

                    <x-ui.form-section title="Penandatangan Dokumen" description="Isi data kepala sekolah dan bendahara untuk tahun anggaran yang sedang aktif.">
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Nama Kepala Sekolah"><x-ui.input name="principal_name" :value="old('principal_name', data_get($profile, 'principal_name', ''))" /></x-ui.field>
                            <x-ui.field label="NIP Kepala Sekolah"><x-ui.input name="principal_nip" :value="old('principal_nip', data_get($profile, 'principal_nip', ''))" /></x-ui.field>
                            <x-ui.field label="Email Kepala Sekolah"><x-ui.input type="email" name="principal_email" :value="old('principal_email', data_get($profile, 'principal_email', ''))" /></x-ui.field>
                            <x-ui.field label="Nomor Telepon Kepala Sekolah"><x-ui.input name="principal_phone" :value="old('principal_phone', data_get($profile, 'principal_phone', ''))" /></x-ui.field>
                            <x-ui.field label="Nama Bendahara"><x-ui.input name="treasurer_name" :value="old('treasurer_name', data_get($profile, 'treasurer_name', ''))" /></x-ui.field>
                            <x-ui.field label="NIP Bendahara"><x-ui.input name="treasurer_nip" :value="old('treasurer_nip', data_get($profile, 'treasurer_nip', ''))" /></x-ui.field>
                            <x-ui.field label="Email Bendahara"><x-ui.input type="email" name="treasurer_email" :value="old('treasurer_email', data_get($profile, 'treasurer_email', ''))" /></x-ui.field>
                            <x-ui.field label="Nomor Telepon Bendahara"><x-ui.input name="treasurer_phone" :value="old('treasurer_phone', data_get($profile, 'treasurer_phone', ''))" /></x-ui.field>
                        </div>
                    </x-ui.form-section>
                </div>

                <x-ui.form-section title="Kop Surat" description="Unggah gambar kop surat dalam format PNG atau JPG, maksimal 5 MB. Kop ini digunakan pada dokumen yang mendukung gambar kop.">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,.7fr)] lg:items-center">
                        <div>
                            <x-ui.field label="Pilih gambar kop surat" for="letterhead"><input id="letterhead" type="file" name="letterhead" accept="image/png,image/jpeg"></x-ui.field>
                        </div>
                        @if ($activeSchool->letterhead_path)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><img src="{{ route('schools.letterhead', ['v' => $activeSchool->updated_at?->timestamp]) }}" alt="Kop surat {{ $activeSchool->name }}" class="h-20 max-w-full rounded bg-white object-contain"><p class="mt-2 text-xs font-semibold text-emerald-700">Kop surat sudah tersimpan dan siap digunakan.</p></div>
                        @endif
                    </div>
                </x-ui.form-section>

                <div class="ui-form-actions"><x-ui.button type="submit">Simpan Perubahan Profil</x-ui.button></div>
            </form>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">Belum ada sekolah aktif. Pilih sekolah terlebih dahulu untuk mengatur profil dan penandatangan.</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.05fr_.95fr] xl:items-start">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-800">Daftar Sekolah</h2><p class="mt-1 text-sm text-slate-500">Lihat sekolah yang sudah terdaftar dan status database masing-masing.</p></div>
                <div class="divide-y divide-slate-100">@forelse ($schools as $school)<div class="px-5 py-4"><p class="font-bold text-slate-800">{{ $school->name }}</p><p class="mt-1 text-sm text-slate-500">Kode {{ $school->school_code ?: '—' }} · NPSN {{ $school->npsn }} · {{ $school->regency }}</p><span class="mt-2 inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Status database: {{ $school->databaseRecord?->status ?? 'Belum dibuat' }}</span></div>@empty<p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada sekolah yang terdaftar.</p>@endforelse</div>
            </section>

            <div class="space-y-6">
                <x-ui.form-section title="Tambah Sekolah Baru" description="Daftarkan sekolah baru. Aplikasi akan menyiapkan database operasional untuk sekolah tersebut.">
                    <form method="POST" action="{{ route('schools.store') }}" class="space-y-4">@csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field label="Kode Sekolah" required><x-ui.input name="school_code" maxlength="40" pattern="[A-Za-z0-9._-]+" required /></x-ui.field>
                            <x-ui.field label="NPSN" required><x-ui.input name="npsn" required /></x-ui.field>
                            <x-ui.field label="Nama Sekolah" class="md:col-span-2" required><x-ui.input name="name" required /></x-ui.field>
                            <x-ui.field label="Alamat Sekolah" class="md:col-span-2"><x-ui.textarea name="address" rows="2"></x-ui.textarea></x-ui.field>
                            <x-ui.field label="Kecamatan"><x-ui.input name="district" /></x-ui.field>
                            <x-ui.field label="Kabupaten/Kota"><x-ui.input name="regency" /></x-ui.field>
                            <x-ui.field label="Provinsi" class="md:col-span-2"><x-ui.input name="province" /></x-ui.field>
                        </div>
                        <div class="flex justify-end"><x-ui.button type="submit">Tambah Sekolah</x-ui.button></div>
                    </form>
                </x-ui.form-section>

                <x-ui.form-section title="Tambah Tahun Anggaran" description="Tambahkan tahun anggaran dan sumber dana yang akan digunakan oleh sekolah.">
                    <form method="POST" action="{{ route('years.store') }}" class="space-y-4">@csrf
                        <x-ui.field label="Sekolah" required><x-ui.select name="school_id" required><option value="">Pilih sekolah</option>@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</x-ui.select></x-ui.field>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui.field label="Tahun Anggaran" required><x-ui.input type="number" name="year" :value="date('Y')" min="2020" max="2100" required /></x-ui.field>
                            <x-ui.field label="Sumber Dana" class="md:col-span-2" required><x-ui.select name="fund_source_id" required><option value="">Pilih sumber dana</option>@foreach ($fundSources as $source)<option value="{{ $source->id }}">{{ $source->code }} · {{ $source->name }}</option>@endforeach</x-ui.select></x-ui.field>
                        </div>
                        <div class="flex justify-end"><x-ui.button type="submit" variant="success">Tambah Tahun Anggaran</x-ui.button></div>
                    </form>
                </x-ui.form-section>
            </div>
        </div>
    </div>
</x-layouts.tailwind-app>
