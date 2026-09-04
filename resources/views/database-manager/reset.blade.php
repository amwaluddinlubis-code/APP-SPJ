<x-layouts.tailwind-app>
    <div class="mx-auto max-w-4xl space-y-5">
        <section class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm">
            <div class="bg-gradient-to-br from-slate-950 via-rose-950 to-rose-900 px-5 py-7 text-white sm:px-7">
                <p class="text-xs font-bold tracking-[.16em] text-rose-200">ADMINISTRASI · DATABASE SEKOLAH</p>
                <h1 class="mt-2 text-2xl font-bold text-white sm:text-3xl">Reset Database Sekolah</h1>
                <p class="mt-2 max-w-3xl text-sm text-rose-100">
                    Menghapus seluruh database tenant sekolah aktif lalu membangun ulang schema dari migration. Semua data sekolah di database tenant, sequence, dan auto-increment akan kembali ke kondisi awal.
                </p>
            </div>
        </section>

        @if(!$active['school'])
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="font-bold text-amber-900">Belum ada sekolah aktif</h2>
                <p class="mt-1 text-sm text-amber-800">Pilih sekolah yang akan direset terlebih dahulu.</p>
                <a href="{{ route('schools.select') }}" class="mt-4 inline-flex rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">Pilih Sekolah</a>
            </section>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Sekolah aktif</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $active['school']->name }}</p>
                        <p class="text-sm text-slate-500">NPSN {{ $active['school']->npsn }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Database tenant</p>
                        <p class="mt-1 break-all font-mono text-sm font-semibold text-slate-800">{{ $activeStatus['path'] ?? $active['database'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Status: {{ $activeStatus['status'] ?? '—' }} · Integrity: {{ $activeStatus['integrity'] ?? '—' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-rose-300 bg-rose-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-700 font-bold text-white">!</div>
                    <div>
                        <h2 class="text-lg font-bold text-rose-950">Aksi permanen</h2>
                        <p class="mt-1 text-sm leading-6 text-rose-900">
                            Reset akan menghapus transaksi, RKAS/BKU hasil sinkronisasi, paket SPJ, nomor dokumen, template tenant, pegawai/siswa tenant, periode, audit tenant, pembayaran, penerimaan barang, serta data tenant lainnya. File SQLite, WAL, dan SHM lama dihapus lalu migration dijalankan dari awal.
                        </p>
                        <p class="mt-2 text-sm font-semibold text-rose-950">
                            Setelah reset, tahun anggaran dan sumber dana aktif pada sesi juga dibersihkan. Anda harus memilih/membuat tahun anggaran dan melakukan sinkronisasi kembali.
                        </p>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('database-manager.reset', $active['school']->id) }}"
                    class="mt-5 space-y-4"
                    data-confirm="Reset akan menghapus permanen seluruh data database sekolah {{ $active['school']->name }} (NPSN {{ $active['school']->npsn }}). Tindakan ini tidak dapat dibatalkan tanpa backup. Lanjutkan?"
                >
                    @csrf
                    <x-ui.field label="Konfirmasi reset" for="confirmation" hint="Ketik tepat: RESET {{ $active['school']->npsn }}">
                        <x-ui.input
                            id="confirmation"
                            name="confirmation"
                            value="{{ old('confirmation') }}"
                            autocomplete="off"
                            placeholder="RESET {{ $active['school']->npsn }}"
                            required
                        />
                    </x-ui.field>

                    @error('confirmation')
                        <p class="text-sm font-bold text-rose-700">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-wrap items-center gap-3 border-t border-rose-200 pt-4">
                        <x-ui.button type="submit" variant="danger">Reset Database Sekarang</x-ui.button>
                        <a href="{{ route('database-manager.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50">Batal</a>
                        <a href="{{ route('school-backups.index') }}" class="text-sm font-bold text-indigo-700 hover:underline">Buka Backup & Pemulihan</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layouts.tailwind-app>
