<x-layouts.tailwind-app title="Integrasi Dapodik">
    <div class="mx-auto max-w-6xl space-y-6">
        <x-page-header
            title="Integrasi Dapodik"
            subtitle="Sinkronisasi satu arah GTK dan Peserta Didik. Token disimpan terenkripsi."
            kicker="Web Service Resmi Lokal"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item
                    label="Status Koneksi"
                    :value="$connection ? 'Terkonfigurasi' : 'Belum dikonfigurasi'"
                    :hint="$connection?->last_status ?? 'Belum ada status sinkronisasi'"
                    :value-class="$connection ? 'text-emerald-700' : 'text-amber-700'"
                />
                <x-stat-item
                    label="Sinkronisasi Terakhir"
                    :value="$connection?->last_synced_at?->translatedFormat('d M Y H:i') ?? '—'"
                    hint="Waktu sinkronisasi terakhir"
                    value-class="text-indigo-700"
                />
                <x-stat-item
                    label="Sumber Data"
                    value="GTK & Peserta Didik"
                    hint="Dapodik → aplikasi SPJ"
                    value-class="text-slate-800"
                />
            </div>
        </x-page-header>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
            <form method="POST" action="{{ route('dapodik.store') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.form-section title="Konfigurasi koneksi" description="Gunakan alamat layanan Dapodik lokal sekolah. Token tidak pernah ditampilkan kembali setelah disimpan.">
                    <div class="grid gap-5">
                        <x-ui.field label="Alamat Dapodik" for="base_url" required hint="Biasanya menggunakan layanan lokal pada komputer server Dapodik." :error="$errors->first('base_url')">
                            <x-ui.input id="base_url" name="base_url" :value="old('base_url',$connection?->base_url??'http://localhost:5774')" placeholder="http://localhost:5774" required />
                        </x-ui.field>

                        <x-ui.field label="NPSN" for="npsn" required hint="Pastikan sama dengan sekolah yang sedang aktif." :error="$errors->first('npsn')">
                            <x-ui.input id="npsn" name="npsn" :value="old('npsn',$connection?->npsn??'10260756')" inputmode="numeric" required />
                        </x-ui.field>

                        <x-ui.field
                            :label="$connection ? 'Bearer token baru' : 'Bearer token'"
                            for="token"
                            :required="!$connection"
                            :hint="$connection ? 'Kosongkan jika token tidak ingin diganti.' : 'Token diperlukan pada konfigurasi pertama.'"
                            :error="$errors->first('token')"
                        >
                            <x-ui.input id="token" type="password" name="token" autocomplete="new-password" :required="!$connection" />
                        </x-ui.field>
                    </div>

                    <div class="mt-6 flex justify-end border-t border-slate-100 pt-5">
                        <x-ui.button type="submit">Simpan konfigurasi</x-ui.button>
                    </div>
                </x-ui.form-section>
            </form>

            <div class="space-y-6">
                <x-ui.form-section title="Status sinkronisasi" description="Pantau hasil koneksi terakhir sebelum menjalankan sinkronisasi data.">
                    <dl class="grid gap-4 text-sm">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Status terakhir</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $connection?->last_status??'Belum dikonfigurasi' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Waktu terakhir</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $connection?->last_synced_at?->translatedFormat('d F Y H:i')??'—' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Keterangan</dt>
                            <dd class="mt-1 leading-6 text-slate-700">{{ $connection?->last_message??'Belum ada hasil sinkronisasi.' }}</dd>
                        </div>
                    </dl>

                    @if($connection)
                        <div class="mt-6 grid gap-2 border-t border-slate-100 pt-5 sm:grid-cols-2">
                            <form method="POST" action="{{ route('dapodik.test') }}">@csrf<x-ui.button type="submit" variant="secondary" class="w-full">Tes layanan</x-ui.button></form>
                            <form method="POST" action="{{ route('dapodik.sync') }}" data-confirm="Sinkronisasi akan mengambil seluruh GTK dan Peserta Didik dari Dapodik. Data manual dipadankan berdasarkan identitas dan tidak dihapus. Lanjutkan?">@csrf<x-ui.button type="submit" variant="success" class="w-full">Sinkronkan sekarang</x-ui.button></form>
                        </div>
                    @endif
                </x-ui.form-section>

                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                    <p class="font-bold">Cara pemadanan data</p>
                    <p class="mt-1">Pegawai menggunakan NUPTK, lalu nama ternormalisasi jika NUPTK kosong. Siswa menggunakan NISN, lalu ID Dapodik. Data yang tidak lagi dikirim akan dinonaktifkan, bukan dihapus.</p>
                </section>
            </div>
        </div>
    </div>
</x-layouts.tailwind-app>
