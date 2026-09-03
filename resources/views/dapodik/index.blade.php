<x-layouts.tailwind-app title="Integrasi Dapodik">
    <div class="mx-auto max-w-5xl space-y-6">
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

        <section class="grid gap-5 lg:grid-cols-2">
            <form method="POST" action="{{ route('dapodik.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')
                <h2 class="font-bold text-slate-900">Konfigurasi koneksi</h2>
                <p class="mt-1 text-sm text-slate-500">Atur alamat layanan, NPSN, dan token akses Dapodik.</p>
                <div class="mt-4 space-y-4">
                    <label><span class="mb-1 block text-sm font-semibold">Alamat Dapodik</span><input name="base_url" value="{{ old('base_url',$connection?->base_url??'http://localhost:5774') }}" required class="w-full rounded-lg border-slate-300"></label>
                    <label><span class="mb-1 block text-sm font-semibold">NPSN</span><input name="npsn" value="{{ old('npsn',$connection?->npsn??'10260756') }}" required class="w-full rounded-lg border-slate-300"></label>
                    <label><span class="mb-1 block text-sm font-semibold">Bearer token {{ $connection?'(kosongkan bila tidak diganti)':'*' }}</span><input type="password" name="token" autocomplete="new-password" class="w-full rounded-lg border-slate-300"></label>
                </div>
                <button class="mt-5 rounded-lg bg-[var(--theme-600)] px-5 py-2 font-bold text-white">Simpan konfigurasi</button>
            </form>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-bold text-slate-900">Status sinkronisasi</h2>
                <p class="mt-1 text-sm text-slate-500">Pantau hasil koneksi dan jalankan sinkronisasi saat diperlukan.</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-slate-500">Status terakhir</dt><dd class="font-semibold">{{ $connection?->last_status??'Belum dikonfigurasi' }}</dd></div>
                    <div><dt class="text-slate-500">Waktu terakhir</dt><dd class="font-semibold">{{ $connection?->last_synced_at?->translatedFormat('d F Y H:i')??'—' }}</dd></div>
                    <div><dt class="text-slate-500">Keterangan</dt><dd>{{ $connection?->last_message??'—' }}</dd></div>
                </dl>
                @if($connection)
                    <div class="mt-6 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('dapodik.test') }}">@csrf<button class="rounded-lg border border-[var(--theme-300)] px-4 py-2 font-bold text-[var(--theme-700)]">Tes semua layanan</button></form>
                        <form method="POST" action="{{ route('dapodik.sync') }}" data-confirm="Sinkronisasi akan mengambil seluruh GTK dan Peserta Didik dari Dapodik. Data manual dipadankan berdasarkan identitas dan tidak dihapus. Lanjutkan?">@csrf<button class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white">Sinkronkan sekarang</button></form>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <strong>Aturan pemadanan:</strong> Pegawai menggunakan NUPTK, lalu nama ternormalisasi jika NUPTK kosong. Siswa menggunakan NISN, lalu ID Dapodik. Record Dapodik yang tidak lagi dikirim akan dinonaktifkan, bukan dihapus.
        </section>
    </div>
</x-layouts.tailwind-app>
