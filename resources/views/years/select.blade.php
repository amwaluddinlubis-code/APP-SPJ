<x-layouts.public-tailwind title="Pilih Tahun dan Sumber Dana">
    <div class="context-elegance flex flex-col gap-7 sm:gap-8">
        <header>
            <p class="text-xs font-medium text-slate-500">Langkah 2 dari 2</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Tentukan Konteks Kerja Anda</h2>
            <p class="mt-2 text-xs leading-6 text-slate-500"></p>
        </header>
        <section aria-label="Sekolah aktif" class="flex items-center gap-3 border-b border-slate-100 pb-5">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600"
                aria-hidden="true"><x-ui.icon name="school" size="sm" /></div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-lg font-semibold text-slate-900">{{ $school->name }}</p>
                <p class="mt-0.5 text-sm text-slate-500">NPSN {{ $school->npsn }}</p>
            </div>
            <a href="{{ route('schools.select') }}"
                class="shrink-0 text-sm font-medium text-slate-500 underline-offset-4 transition hover:text-slate-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">Ubah
                Sekolah</a>
        </section>
        @unless ($hasFundSourceContext)
            <div
                class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                <x-ui.icon name="warning" class="mt-0.5 shrink-0" />
                <p>Database sekolah belum siap untuk konteks sumber dana. Buka <a
                        href="{{ route('database-manager.index') }}"
                        class="font-bold underline underline-offset-2">Manajemen Database</a> lalu jalankan migrasi pada
                    sekolah aktif.</p>
            </div>
        @endunless
        @unless ($arkasSource)
            <x-ui.form-section title="Hubungkan ARKAS terlebih dahulu"
                description="Simpan lokasi sumber ARKAS agar tahun anggaran dan sumber dana dapat disinkronkan.">
                <form method="POST" action="{{ route('arkas.settings.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf<input type="hidden" name="school_id" value="{{ $school->id }}"><input type="hidden"
                        name="return_to" value="{{ route('years.select') }}">
                    <x-ui.field label="Lokasi database ARKAS" for="year-arkas-database"
                        hint="Gunakan file database ARKAS sekolah, bukan database SPJ lokal." :error="$errors->first('database_path')"
                        required><x-ui.input id="year-arkas-database" name="database_path" :value="old('database_path')"
                            placeholder="D:\Folder ARKAS\database_arkas.db" class="font-mono" required /></x-ui.field>
                    <x-ui.field label="Lokasi ARKASBridge.exe" for="year-arkas-bridge"
                        hint="Engine untuk membaca database ARKAS." :error="$errors->first('bridge_path')" required><x-ui.input
                            id="year-arkas-bridge" name="bridge_path" :value="old('bridge_path', $defaultBridgePath)" class="font-mono"
                            required /></x-ui.field>
                    <x-ui.field label="Kata sandi database ARKAS" for="year-arkas-password"
                        hint="Wajib diisi saat konfigurasi pertama." :error="$errors->first('database_password')"><x-ui.input id="year-arkas-password"
                            type="password" name="database_password" autocomplete="new-password"
                            placeholder="Masukkan kata sandi database" /></x-ui.field>
                    <div class="flex items-end sm:justify-end"><x-ui.button type="submit" icon="save"
                            class="w-full justify-center sm:w-auto">Simpan pengaturan ARKAS</x-ui.button></div>
                </form>
            </x-ui.form-section>
        @endunless
        <livewire:year-selector :has-fund-source-context="$hasFundSourceContext" />
    </div>
</x-layouts.public-tailwind>
