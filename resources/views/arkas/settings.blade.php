<x-layouts.tailwind-app>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header
            title="Integrasi ARKAS"
            subtitle="Setiap sekolah memiliki sumber database ARKAS dan database SPJ lokalnya sendiri. Simpan path sekali, lalu gunakan Sinkron Semua ARKAS."
            kicker="Pengaturan Sumber Data"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Database ARKAS" :value="$health['database'] ? 'Ditemukan' : 'Belum ditemukan'" :hint="$health['database'] ? 'Sumber database siap' : 'Periksa lokasi database'" :value-class="$health['database'] ? 'text-emerald-700' : 'text-rose-700'" />
                <x-stat-item label="Engine ARKASBridge" :value="$health['bridge'] ? 'Ditemukan' : 'Belum ditemukan'" :hint="$health['bridge'] ? 'Engine siap digunakan' : 'Periksa lokasi engine'" :value-class="$health['bridge'] ? 'text-emerald-700' : 'text-rose-700'" />
                <x-stat-item label="Kata Sandi" :value="$health['password'] ? 'Tersimpan' : 'Belum disimpan'" :hint="$health['password'] ? 'Tersimpan terenkripsi' : 'Lengkapi konfigurasi'" :value-class="$health['password'] ? 'text-emerald-700' : 'text-rose-700'" />
            </div>
        </x-page-header>

        <section class="grid gap-6 lg:grid-cols-[1.25fr_.75fr] lg:items-start">
            <x-ui.form-section title="Sumber ARKAS per Sekolah" description="Path hanya digunakan pada komputer ini. Kata sandi tidak pernah ditampilkan kembali.">
                <x-slot:actions>
                    <x-ui.button variant="secondary" :href="route('schools.settings')">Profil Sekolah</x-ui.button>
                </x-slot:actions>

                <form method="POST" action="{{ route('arkas.settings.store') }}" class="space-y-5">
                    @csrf

                    <x-ui.field label="Sekolah" for="arkas-school" hint="Pilih sekolah yang sumber ARKAS-nya ingin dikonfigurasi." required>
                        <x-ui.select id="arkas-school" name="school_id" onchange="if(this.value) window.location='{{ route('arkas.settings') }}?school_id='+this.value" required>
                            <option value="">Pilih sekolah</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }} — NPSN {{ $school->npsn }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field label="Lokasi database ARKAS" for="database_path" hint="Gunakan file database ARKAS sekolah, bukan database SPJ lokal." :error="$errors->first('database_path')" required>
                        <x-ui.input id="database_path" name="database_path" :value="old('database_path', $selectedSource?->database_path)" placeholder="D:\Folder ARKAS\database_arkas.db" class="font-mono" required />
                    </x-ui.field>

                    <x-ui.field label="Lokasi ARKASBridge.exe" for="bridge_path" hint="Engine dipakai untuk membaca database ARKAS dari aplikasi." :error="$errors->first('bridge_path')" required>
                        <x-ui.input id="bridge_path" name="bridge_path" :value="old('bridge_path', $selectedSource?->bridge_path ?? 'D:\Aplikasi SPJ-BOS\Engine\ARKASBridge.exe')" class="font-mono" required />
                    </x-ui.field>

                    <x-ui.field label="Kata sandi database ARKAS" for="database_password" :hint="$selectedSource ? 'Kosongkan jika kata sandi lama tetap digunakan.' : 'Wajib saat konfigurasi pertama. Kata sandi disimpan terenkripsi.'" :error="$errors->first('database_password')">
                        <x-ui.input id="database_password" type="password" name="database_password" autocomplete="new-password" :placeholder="$selectedSource ? 'Kosongkan bila tidak diubah' : 'Masukkan kata sandi database'" />
                    </x-ui.field>

                    <div class="ui-form-actions">
                        <x-ui.button type="submit">Simpan Integrasi</x-ui.button>
                    </div>
                </form>
            </x-ui.form-section>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
                <h2 class="font-bold text-slate-800">Status Sinkronisasi</h2>
                <p class="mt-1 text-sm text-slate-500">Ringkasan konfigurasi sekolah yang sedang dipilih.</p>
                @if($selectedSource)
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sekolah</dt><dd class="mt-1 font-semibold text-slate-800">{{ $selectedSource->school?->name }}</dd></div>
                        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sinkronisasi terakhir</dt><dd class="mt-1 text-slate-700">{{ $selectedSource->last_synced_at?->translatedFormat('d F Y H:i') ?: 'Belum pernah disinkronkan' }}</dd></div>
                        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Identitas database</dt><dd class="mt-1 break-all font-mono text-xs text-slate-600">{{ $selectedSource->last_identity ?: 'Belum tersedia' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-4 text-sm text-slate-500">Pilih sekolah untuk melihat dan mengatur sumber ARKAS.</p>
                @endif
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-bold">Urutan aman</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-4 text-xs"><li>Pastikan sekolah aktif dan tahun anggaran telah dipilih.</li><li>Simpan database ARKAS, engine, dan kata sandi.</li><li>Periksa tiga status hijau, lalu jalankan Sinkron Semua ARKAS.</li></ol>
                </div>
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
