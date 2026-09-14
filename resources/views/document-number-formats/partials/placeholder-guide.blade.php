<section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
    <h2 class="font-bold">Placeholder yang tersedia</h2>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach($placeholders as $placeholder)
            <code class="rounded-md bg-[var(--ui-surface-base)] px-2.5 py-1 font-bold text-indigo-700 ring-1 ring-sky-200">&#123;{{ $placeholder }}&#125;</code>
        @endforeach
    </div>
    <p class="mt-3 text-xs leading-5 text-sky-800"><strong>{SEQ}</strong> wajib ada. <strong>{SCHOOL}</strong> memakai Kode Sekolah, <strong>{NPSN}</strong> memakai NPSN, dan <strong>{TW}</strong> menghasilkan <code>I</code> sampai <code>IV</code> berdasarkan tanggal dokumen. Jika ingin menampilkan prefix, tulis literal <code>TW.{TW}</code> pada pola. Contoh tanpa prefix: <code>{SEQ}/OP/{SCHOOL}/{TW}/{YEAR}</code>.</p>
</section>
