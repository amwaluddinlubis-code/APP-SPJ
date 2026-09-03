<x-layouts.tailwind-app>
    @php($size = fn ($bytes) => number_format($bytes / 1024 / 1024, 2, ',', '.').' MB')
    <div class="mx-auto max-w-5xl space-y-6">
        <x-page-header
            title="Backup dan Pemulihan Database Sekolah"
            subtitle="Backup hanya mencakup database lokal sekolah aktif dan sebaiknya dibuat sebelum perubahan data besar."
            kicker="Keamanan Data Operasional"
        >
            <x-slot:actions>
                <form method="POST" action="{{ route('school-backups.store') }}">@csrf<button class="rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-indigo-800 shadow hover:bg-indigo-50">Buat Backup Sekarang</button></form>
            </x-slot:actions>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Sekolah Aktif" :value="$school->name" :hint="'NPSN '.$school->npsn" value-class="text-indigo-700" />
                <x-stat-item label="Jumlah Backup" :value="number_format($backups->count(), 0, ',', '.')" hint="Backup yang tersedia" value-class="text-emerald-700" />
                <x-stat-item label="Backup Terakhir" :value="$backups->first()?->created_at?->translatedFormat('d M Y H:i') ?? '—'" hint="Waktu pembuatan terakhir" value-class="text-slate-800" />
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4"><h2 class="text-base font-bold text-slate-800">Riwayat Backup</h2><p class="mt-1 text-base text-slate-500">Pemulihan mengganti database aktif dengan salinan yang dipilih. Sistem membuat backup pengaman sebelum pemulihan.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-base"><thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">Waktu</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Berkas</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Jenis</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Ukuran</th><th class="px-5 py-3 text-right text-xs font-bold uppercase text-slate-500">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($backups as $backup)<tr><td class="px-5 py-4 text-slate-700">{{ $backup->created_at->translatedFormat('d F Y H:i') }}</td><td class="px-4 py-4 font-mono text-base text-slate-700">{{ $backup->file_name }}</td><td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ str_replace('_', ' ', $backup->reason) }}</span></td><td class="px-4 py-4 text-right text-slate-700">{{ $size($backup->file_size) }}</td><td class="px-5 py-4 text-right"><form method="POST" action="{{ route('school-backups.restore', $backup->id) }}" data-confirm="Pulihkan database sekolah dari backup ini? Data saat ini akan diganti, tetapi sistem membuat backup pengaman terlebih dahulu.">@csrf<input type="hidden" name="confirm_restore" value="1"><button class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-base font-bold text-amber-800 hover:bg-amber-100">Pulihkan</button></form></td></tr>@empty<tr><td colspan="5" class="px-5 py-14 text-center text-slate-500">Belum ada backup. Buat backup pertama sekarang.</td></tr>@endforelse</tbody></table></div>
        </section>
    </div>
</x-layouts.tailwind-app>
