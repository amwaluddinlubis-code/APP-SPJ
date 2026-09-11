<x-layouts.tailwind-app>
    <div class="mx-auto max-w-4xl space-y-5">
        <x-page-header
            title="Reset Database Sekolah"
            subtitle="Menghapus seluruh database tenant sekolah aktif lalu membangun ulang schema dari migration. Semua data sekolah di database tenant, sequence, dan auto-increment akan kembali ke kondisi awal."
            kicker="Administrasi · Database Sekolah"
        />

        @if(!$active['school'])
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="font-bold text-amber-900">Belum ada sekolah aktif</h2>
                <p class="mt-1 text-sm text-amber-800">Pilih sekolah yang akan direset terlebih dahulu.</p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" :href="route('schools.select')">Pilih Sekolah</x-ui.button>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Sekolah aktif</p>
                        <p class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">{{ $active['school']->name }}</p>
                        <p class="text-sm text-[var(--ui-fg-muted)]">NPSN {{ $active['school']->npsn }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Database tenant</p>
                        <p class="mt-1 break-all font-mono text-sm font-semibold text-[var(--ui-fg)]">{{ $activeStatus['path'] ?? $active['database'] }}</p>
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Status: {{ $activeStatus['status'] ?? '—' }} · Integrity: {{ $activeStatus['integrity'] ?? '—' }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-rose-300 bg-rose-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-700 text-white">
                        <x-ui.icon name="warning" size="sm" />
                    </div>
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
                        <x-ui.button variant="secondary" :href="route('database-manager.index')">Batal</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('school-backups.index')">Backup & Pemulihan</x-ui.button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layouts.tailwind-app>
