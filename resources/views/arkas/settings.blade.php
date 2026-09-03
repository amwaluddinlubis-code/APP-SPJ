<x-layouts.tailwind-app>
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-900 px-5 py-7 text-white sm:px-7 lg:py-8">
                <p class="text-xs font-bold tracking-[.16em] text-cyan-200">PENGATURAN SUMBER DATA</p>
                <h1 class="mt-2 text-2xl font-bold">Integrasi ARKAS</h1>
                <p class="mt-1 max-w-3xl text-base text-sky-100">Setiap sekolah memiliki sumber database ARKAS dan database SPJ lokalnya sendiri. Simpan path sekali, lalu gunakan tombol Sinkron Semua ARKAS pada menu samping.</p>
            </div>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                @foreach([['Database ARKAS', $health['database'], $health['database'] ? 'Ditemukan' : 'Belum ditemukan'], ['Engine ARKASBridge', $health['bridge'], $health['bridge'] ? 'Ditemukan' : 'Belum ditemukan'], ['Kata sandi', $health['password'], $health['password'] ? 'Tersimpan terenkripsi' : 'Belum disimpan']] as [$label, $ready, $status])
                    <div class="px-5 py-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-1 text-base font-bold {{ $ready ? 'text-emerald-700' : 'text-rose-700' }}">● {{ $status }}</p></div>
                @endforeach
            </div>
        </section>
        <section class="grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow">
                <div class="flex items-start justify-between gap-4"><div><h2 class="font-bold text-slate-800">Sumber ARKAS per Sekolah</h2><p class="mt-1 text-base text-slate-500">Path dipakai hanya pada komputer ini. Kata sandi tidak pernah ditampilkan kembali.</p></div><a href="{{ route('schools.settings') }}" class="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Profil Sekolah</a></div>
                <form method="POST" action="{{ route('arkas.settings.store') }}" class="mt-5 space-y-4">@csrf
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Sekolah</label><select class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base" name="school_id" onchange="if(this.value) window.location='{{ route('arkas.settings') }}?school_id='+this.value" required><option value="">Pilih sekolah</option>@foreach($schools as $school)<option value="{{ $school->id }}" @selected((int) $selectedSchoolId === $school->id)>{{ $school->name }} — NPSN {{ $school->npsn }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Lokasi database ARKAS</label><input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-base" name="database_path" value="{{ old('database_path', $selectedSource?->database_path) }}" placeholder="D:\Folder ARKAS\database_arkas.db" required><p class="mt-1 text-xs text-slate-500">Pilih file database ARKAS sekolah yang bersangkutan, bukan database SPJ lokal.</p></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Lokasi ARKASBridge.exe</label><input class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-base" name="bridge_path" value="{{ old('bridge_path', $selectedSource?->bridge_path ?? 'D:\Aplikasi SPJ-BOS\Engine\ARKASBridge.exe') }}" required></div>
                    <div><label class="text-xs font-bold uppercase tracking-wide text-slate-600">Kata sandi database ARKAS</label><input type="password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base" name="database_password" autocomplete="new-password" placeholder="{{ $selectedSource ? 'Kosongkan bila tidak diubah' : 'Wajib saat pertama kali disimpan' }}"><p class="mt-1 text-xs text-slate-500">{{ $selectedSource ? 'Kata sandi sebelumnya tetap digunakan jika kolom ini dikosongkan.' : 'Kata sandi disimpan dengan enkripsi aplikasi.' }}</p></div>
                    <div class="border-t border-slate-100 pt-4"><button class="rounded-lg bg-sky-600 px-4 py-2.5 text-base font-bold text-white shadow hover:bg-sky-700">Simpan Integrasi</button></div>
                </form>
            </article>
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow">
                <h2 class="font-bold text-slate-800">Status Sinkronisasi</h2>
                @if($selectedSource)<dl class="mt-4 space-y-4 text-base"><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sekolah</dt><dd class="mt-1 font-semibold text-slate-800">{{ $selectedSource->school?->name }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Sinkronisasi terakhir</dt><dd class="mt-1 text-slate-700">{{ $selectedSource->last_synced_at?->translatedFormat('d F Y H:i') ?: 'Belum pernah disinkronkan' }}</dd></div><div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Identitas database</dt><dd class="mt-1 break-all font-mono text-xs text-slate-600">{{ $selectedSource->last_identity ?: 'Belum tersedia' }}</dd></div></dl>@else<p class="mt-4 text-base text-slate-500">Pilih sekolah untuk melihat dan mengatur sumber ARKAS.</p>@endif
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-base text-amber-900"><p class="font-bold">Urutan aman</p><ol class="mt-2 list-decimal space-y-1 pl-4 text-xs"><li>Pastikan sekolah aktif dan tahun anggaran telah dipilih.</li><li>Simpan database ARKAS, engine, dan kata sandi.</li><li>Periksa tiga status hijau, lalu jalankan Sinkron Semua ARKAS.</li></ol></div>
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
