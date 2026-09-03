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
            <section class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100 bg-indigo-50 px-6 py-4">
                    <div>
                        <h2 class="font-bold text-indigo-950">Profil Sekolah Aktif</h2>
                        <p class="mt-1 text-base text-indigo-700">{{ $activeSchool->name }} · {{ $activeYear ? 'Tahun '.$activeYear->year.' / '.$activeYear->fund_source : 'Pilih tahun untuk mengatur penandatangan per tahun' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2"><span class="rounded-full bg-white px-3 py-1.5 text-base font-bold text-indigo-700">Kode {{ $activeSchool->school_code ?: 'Belum diisi' }}</span><span class="rounded-full bg-white px-3 py-1.5 text-base font-bold text-indigo-700">NPSN {{ $activeSchool->npsn }}</span></div>
                </div>

                <form method="POST" action="{{ route('schools.profile.update') }}" enctype="multipart/form-data" class="grid gap-7 p-6 xl:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Identitas dan Alamat</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="text-base font-bold text-slate-600">Kode Sekolah</label><input name="school_code" value="{{ old('school_code', $activeSchool->school_code) }}" maxlength="40" pattern="[A-Za-z0-9._-]+" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required><p class="mt-1 text-xs text-slate-500">Dipakai oleh placeholder {SCHOOL}.</p></div>
                            <div><label class="text-base font-bold text-slate-600">NPSN</label><input name="npsn" value="{{ old('npsn', $activeSchool->npsn) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required><p class="mt-1 text-xs text-slate-500">Dipakai oleh placeholder {NPSN}.</p></div>
                            <div><label class="text-base font-bold text-slate-600">Nama Sekolah</label><input name="name" value="{{ old('name', $activeSchool->name) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required></div>
                            <div class="md:col-span-2"><label class="text-base font-bold text-slate-600">Alamat</label><textarea name="address" rows="2" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base">{{ old('address', $activeSchool->address) }}</textarea></div>
                            <div><label class="text-base font-bold text-slate-600">Kecamatan</label><input name="district" value="{{ old('district', $activeSchool->district) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Kabupaten/Kota</label><input name="regency" value="{{ old('regency', $activeSchool->regency) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div class="md:col-span-2"><label class="text-base font-bold text-slate-600">Provinsi</label><input name="province" value="{{ old('province', $activeSchool->province) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-slate-800">Penandatangan Dokumen <span class="font-normal text-slate-500">(tahun aktif)</span></h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="text-base font-bold text-slate-600">Nama Kepala Sekolah</label><input name="principal_name" value="{{ old('principal_name', data_get($profile, 'principal_name', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">NIP Kepala Sekolah</label><input name="principal_nip" value="{{ old('principal_nip', data_get($profile, 'principal_nip', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Email Kepala Sekolah</label><input type="email" name="principal_email" value="{{ old('principal_email', data_get($profile, 'principal_email', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Telepon Kepala Sekolah</label><input name="principal_phone" value="{{ old('principal_phone', data_get($profile, 'principal_phone', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Nama Bendahara</label><input name="treasurer_name" value="{{ old('treasurer_name', data_get($profile, 'treasurer_name', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">NIP Bendahara</label><input name="treasurer_nip" value="{{ old('treasurer_nip', data_get($profile, 'treasurer_nip', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Email Bendahara</label><input type="email" name="treasurer_email" value="{{ old('treasurer_email', data_get($profile, 'treasurer_email', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                            <div><label class="text-base font-bold text-slate-600">Telepon Bendahara</label><input name="treasurer_phone" value="{{ old('treasurer_phone', data_get($profile, 'treasurer_phone', '')) }}" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        </div>
                    </div>

                    <div class="xl:col-span-2 border-t border-slate-100 pt-5">
                        <label class="text-base font-bold text-slate-600">Kop Surat Gambar <span class="font-normal text-slate-500">(PNG/JPG, maksimum 5 MB)</span></label>
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            @if ($activeSchool->letterhead_path)
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-2"><img src="{{ route('schools.letterhead', ['v' => $activeSchool->updated_at?->timestamp]) }}" alt="Kop surat {{ $activeSchool->name }}" class="h-20 max-w-full rounded bg-white object-contain"><p class="mt-1 text-xs font-semibold text-emerald-700">Kop surat sudah tersimpan</p></div>
                            @endif
                            <input type="file" name="letterhead" accept="image/png,image/jpeg" class="text-base">
                            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-base font-bold text-white shadow hover:bg-indigo-700">Simpan Profil Aktif</button>
                        </div>
                    </div>
                </form>
            </section>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-base text-amber-900">Pilih sekolah aktif terlebih dahulu agar profil dan penandatangan dapat dikelola.</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-base font-bold text-slate-800">Database Sekolah</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($schools as $school)
                        <div class="px-5 py-4"><p class="font-bold text-slate-800">{{ $school->name }}</p><p class="mt-1 text-base text-slate-500">Kode {{ $school->school_code ?: '—' }} · NPSN {{ $school->npsn }} · {{ $school->regency }}</p><span class="mt-2 inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-base font-bold text-emerald-700">Database {{ $school->databaseRecord?->status ?? 'Belum dibuat' }}</span></div>
                    @empty
                        <p class="px-5 py-10 text-center text-base text-slate-500">Belum ada sekolah.</p>
                    @endforelse
                </div>
            </section>
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow">
                    <h2 class="text-base font-bold text-slate-800">Tambah Sekolah</h2>
                    <form method="POST" action="{{ route('schools.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf
                        <div><label class="text-base font-bold text-slate-600">Kode Sekolah</label><input name="school_code" maxlength="40" pattern="[A-Za-z0-9._-]+" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required></div>
                        <div><label class="text-base font-bold text-slate-600">NPSN</label><input name="npsn" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required></div>
                        <div><label class="text-base font-bold text-slate-600">Nama Sekolah</label><input name="name" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required></div>
                        <div class="md:col-span-2"><label class="text-base font-bold text-slate-600">Alamat</label><input name="address" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        <div><label class="text-base font-bold text-slate-600">Kecamatan</label><input name="district" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        <div><label class="text-base font-bold text-slate-600">Kabupaten/Kota</label><input name="regency" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        <div class="md:col-span-2"><label class="text-base font-bold text-slate-600">Provinsi</label><input name="province" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"></div>
                        <button class="md:col-span-2 rounded-lg bg-slate-800 px-4 py-2.5 text-base font-bold text-white">Buat Sekolah dan Database</button>
                    </form>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow">
                    <h2 class="text-base font-bold text-slate-800">Tambah Tahun Anggaran</h2>
                    <form method="POST" action="{{ route('years.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf
                        <div class="md:col-span-3"><label class="text-base font-bold text-slate-600">Sekolah</label><select name="school_id" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required><option value="">Pilih sekolah</option>@foreach ($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></div>
                        <div><label class="text-base font-bold text-slate-600">Tahun</label><input type="number" name="year" value="{{ date('Y') }}" min="2020" max="2100" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required></div>
                        <div class="md:col-span-2"><label class="text-base font-bold text-slate-600">Sumber Dana</label><select name="fund_source_id" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" required><option value="">Pilih sumber dana</option>@foreach ($fundSources as $source)<option value="{{ $source->id }}">{{ $source->code }} · {{ $source->name }}</option>@endforeach</select></div>
                        <button class="md:col-span-3 rounded-lg bg-emerald-600 px-4 py-2.5 text-base font-bold text-white">Simpan Tahun</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-layouts.tailwind-app>
