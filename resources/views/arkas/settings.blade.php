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

        <section class="grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4"><div><h2 class="font-bold text-slate-800">Sumber ARKAS per Sekolah</h2><p class="mt-1 text-base text-slate-500">Path dipakai hanya pada komputer ini. Kata sandi tidak pernah ditampilkan kembali.</p></div><a href="{{ route('schools.settings') }}" class="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Profil Sekolah</a></div>
                <form method="POST" action="{{ route('arkas.settings.store') }}" class="mt-5 space-y-4">@csrf
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Sekolah</label><select class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base" name="school_id" onchange="if(this.value) window.location='{{ route('arkas.settings') }}?school_id='+this.value" required><option value="">Pilih sekolah</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }} — NPSN {{ $school->npsn }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Lokasi database ARKAS</label><input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-base" name="database_path" value="{{ old('database_path', $selectedSource?->database_path) }}" placeholder="D:\Folder ARKAS\database_arkas.db" required><p class="mt-1 text-xs text-slate-500">Pilih file database ARKAS sekolah yang bersangkutan, bukan database SPJ lokal.</p></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Lokasi ARKASBridge.exe</label><input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-base" name="bridge_path" value="{{ old('bridge_path', $selectedSource?->bridge_path ?? 'D:\Aplikasi SPJ-BOS\Engine\ARKASBridge.exe') }}" required></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Kata sandi database ARKAS</label><input type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" name="database_password" autocomplete="new-password" placeholder="{{ $selectedSource ? 'Kosongkan bila tidak diubah' : 'Wajib saat pertama kali disimpan' }}"><p class="mt-1 text-xs text-slate-500">{{ $selectedSource ? 'Kata sandi sebelumnya tetap digunakan jika kolom ini dikosongkan.' : 'Kata sandi disimpan dengan enkripsi aplikasi.' }}</p></div>
                    <div class="border-t border-slate-100 pt-4"><button class="rounded-lg bg-sky-600 px-4 py-2.5 text-base font-bold text-white shadow hover:bg-sky-700">Simpan Integrasi</button></div>
                </form>
            </article>
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-800">Status Sinkronisasi</h2>
                @if($selectedSource)<dl class="mt-4 space-y-4 text-base"><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sekolah</dt><dd class="mt-1 font-semibold text-slate-800">{{ $selectedSource->school?->name }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sinkronisasi terakhir</dt><dd class="mt-1 text-slate-700">{{ $selectedSource->last_synced_at?->translatedFormat('d F Y H:i') ?: 'Belum pernah disinkronkan' }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Identitas database</dt><dd class="mt-1 break-all font-mono text-xs text-slate-600">{{ $selectedSource->last_identity ?: 'Belum tersedia' }}</dd></div></dl>@else<p class="mt-4 text-base text-slate-500">Pilih sekolah untuk melihat dan mengatur sumber ARKAS.</p>@endif
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-base text-amber-900"><p class="font-bold">Urutan aman</p><ol class="mt-2 list-decimal space-y-1 pl-4 text-xs"><li>Pastikan sekolah aktif dan tahun anggaran telah dipilih.</li><li>Simpan database ARKAS, engine, dan kata sandi.</li><li>Periksa tiga status hijau, lalu jalankan Sinkron Semua ARKAS.</li></ol></div>
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
