<x-layouts.public-tailwind title="Pilih Tahun dan Sumber Dana">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold tracking-[.16em] text-indigo-600">LANGKAH 2</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Pilih Tahun dan Sumber Dana</h1>
            <p class="mt-1 text-base text-slate-500">{{ $school->name }} · NPSN {{ $school->npsn }}. Data akan dibatasi
                berdasarkan pilihan ini.</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('schools.select') }}"
                class="rounded-lg border border-[var(--ui-line-strong)] px-3 py-2 text-xs font-bold text-slate-700">Ganti
                Sekolah</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    class="rounded-lg border border-[var(--ui-line-strong)] px-3 py-2 text-xs font-bold text-slate-700">Keluar</button>
            </form>
        </div>
    </div>

    @unless ($hasFundSourceContext)
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Database sekolah belum
            siap untuk konteks sumber dana. Buka <a href="{{ route('database-manager.index') }}"
                class="font-bold underline">Manajemen Database</a> lalu jalankan migrasi pada sekolah aktif.</div>
    @endunless

    @if (!$arkasSource)
        <section class="mt-5 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--theme-accent-soft)] text-sm font-bold text-[var(--theme-content-accent)]">1</span>
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Pengaturan ARKAS</h2>
                    <p class="mt-1 text-sm leading-5 text-[var(--ui-fg-muted)]">Simpan sumber database ARKAS untuk
                        {{ $school->name }} sebelum mengimpor tahun dan sumber dana.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('arkas.settings.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="school_id" value="{{ $school->id }}">
                <input type="hidden" name="return_to" value="{{ route('years.select') }}">
                <x-ui.field label="Lokasi database ARKAS" for="year-arkas-database"
                    hint="Gunakan file database ARKAS sekolah, bukan database SPJ lokal." :error="$errors->first('database_path')" required>
                    <x-ui.input id="year-arkas-database" name="database_path" :value="old('database_path')"
                        placeholder="D:\Folder ARKAS\database_arkas.db" class="font-mono" required />
                </x-ui.field>
                <x-ui.field label="Lokasi ARKASBridge.exe" for="year-arkas-bridge"
                    hint="Engine untuk membaca database ARKAS." :error="$errors->first('bridge_path')" required>
                    <x-ui.input id="year-arkas-bridge" name="bridge_path" :value="old('bridge_path', $defaultBridgePath)" class="font-mono"
                        required />
                </x-ui.field>
                <x-ui.field label="Kata sandi database ARKAS" for="year-arkas-password"
                    hint="Wajib diisi saat konfigurasi pertama." :error="$errors->first('database_password')">
                    <x-ui.input id="year-arkas-password" type="password" name="database_password"
                        autocomplete="new-password" placeholder="Masukkan kata sandi database" />
                </x-ui.field>
                <div class="flex items-end sm:justify-end">
                    <x-ui.button type="submit" class="w-full justify-center sm:w-auto">Simpan Pengaturan
                        ARKAS</x-ui.button>
                </div>
            </form>
        </section>
    @endif

    <div class="mt-6 space-y-3">
        @forelse($years as $year)
            <form method="POST" action="{{ route('years.activate') }}"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                @csrf
                <input type="hidden" name="fiscal_year_id" value="{{ $year->id }}">
                <div>
                    <p class="font-bold text-slate-800">Tahun {{ $year->year }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $year->fundSource?->code }} ·
                        {{ $year->fundSource?->name ?? $year->fund_source }} · NPSN {{ $school->npsn }}</p>
                </div>
                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white"
                    @disabled(!$hasFundSourceContext)>Gunakan</button>
            </form>
        @empty
            <div class="rounded-lg bg-[var(--ui-surface-soft)] p-5 text-base text-slate-500">
                <p class="font-semibold text-slate-700">Belum ada kombinasi tahun dan sumber dana.</p>
                <p class="mt-2 text-sm">Setelah Pengaturan ARKAS tersimpan, jalankan sinkronisasi untuk mengimpor data
                    tahun dan sumber dana.</p>
                <form method="POST" action="{{ route('years.synchronize') }}"
                    data-confirm="Sinkronisasi akan mengimpor data tahun dan sumber dana dari ARKAS. Lanjutkan?"
                    class="mt-4">
                    @csrf
                    <input type="hidden" name="confirm_sync" value="1">
                    <button
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Sinkronisasi
                        ARKAS Sekarang</button>
                </form>
            </div>
        @endforelse
    </div>
</x-layouts.public-tailwind>
