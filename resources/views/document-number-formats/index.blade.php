<x-layouts.tailwind-app>
    @php($labels = ['SPJ' => 'SPJ Utama', 'PESANAN' => 'Surat Pesanan', 'BAP' => 'Berita Acara Pemeriksaan', 'BAST' => 'Berita Acara Serah Terima', 'SPK' => 'Surat Perintah Kerja', 'RAB' => 'Rencana Anggaran Biaya', 'SURAT_TUGAS_PERJALANAN_DINAS' => 'Surat Tugas Perjalanan Dinas', 'KUITANSI' => 'Kuitansi', 'RINCIAN_BELANJA' => 'Rincian Belanja', 'CHECKLIST' => 'Checklist', 'REKAP_PAJAK' => 'Rekap Pajak', 'INVOICE_PESANAN' => 'Invoice / Pesanan'])
    @php($romanMonth = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month])
    <div class="space-y-6">
        <x-page-header
            title="Format Penomoran SPJ"
            subtitle="Atur susunan nomor untuk setiap jenis dokumen pada tahun {{ $year->year }}. Perubahan hanya berlaku untuk nomor yang belum diterbitkan."
            kicker="Pengaturan Dokumen"
        >
            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Tahun Aktif" :value="$year->year" hint="Konteks penomoran" />
                <x-stat-item label="Kode Sekolah" :value="$school->school_code ?: $school->npsn" hint="Placeholder {SCHOOL}" value-class="text-indigo-700" />
                <x-stat-item label="NPSN" :value="$school->npsn" hint="Placeholder {NPSN}" value-class="text-slate-800" />
                <x-stat-item label="Hak Akses" value="Admin & Operator" hint="Dapat mengubah format" value-class="text-emerald-700" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
            <h2 class="font-bold">Placeholder yang tersedia</h2>
            <div class="mt-3 flex flex-wrap gap-2">@foreach($placeholders as $placeholder)<code class="rounded-md bg-white px-2.5 py-1 font-bold text-indigo-700 ring-1 ring-sky-200">&#123;{{ $placeholder }}&#125;</code>@endforeach</div>
            <p class="mt-3 text-xs leading-5 text-sky-800"><strong>{SEQ}</strong> wajib ada. <strong>{SCHOOL}</strong> memakai Kode Sekolah, sedangkan <strong>{NPSN}</strong> memakai NPSN dari Pengaturan Sekolah. Contoh: <code>{SEQ}/SPJ/{SCHOOL}/{NPSN}/{YEAR}</code>.</p>
        </section>

        <div class="space-y-4">
            @foreach($documentTypes as $documentType)
                @php($format = $formats->get($documentType))
                @php($pattern = old('document_type') === $documentType ? old('format_pattern') : ($format?->format_pattern ?? '{SEQ}/'.$documentType.'/{SCHOOL}/{ROMAN_MONTH}/{YEAR}'))
                @php($padding = old('document_type') === $documentType ? old('padding') : ($format?->padding ?? 4))
                @php($reset = old('document_type') === $documentType ? old('reset_period') : ($format?->reset_period ?? 'YEAR'))
                @php($preview = strtr($pattern, ['{SEQ}' => str_pad('1', (int) $padding, '0', STR_PAD_LEFT), '{TYPE}' => $documentType, '{SCHOOL}' => $school->school_code ?: $school->npsn, '{NPSN}' => $school->npsn, '{YEAR}' => (string) $year->year, '{MONTH}' => now()->format('m'), '{ROMAN_MONTH}' => $romanMonth]))
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="{ pattern: @js($pattern), padding: {{ (int) $padding }}, type: @js($documentType), school: @js($school->school_code ?: $school->npsn), npsn: @js($school->npsn), year: @js((string) $year->year), month: @js(now()->format('m')), romanMonth: @js($romanMonth), preview() { return this.pattern.replaceAll('{SEQ}', '1'.padStart(Number(this.padding), '0')).replaceAll('{TYPE}', this.type).replaceAll('{SCHOOL}', this.school).replaceAll('{NPSN}', this.npsn).replaceAll('{YEAR}', this.year).replaceAll('{MONTH}', this.month).replaceAll('{ROMAN_MONTH}', this.romanMonth) } }">
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-indigo-600">{{ $documentType }}</p><h2 class="mt-1 font-bold text-slate-900">{{ $labels[$documentType] ?? str_replace('_', ' ', $documentType) }}</h2></div><span class="w-fit rounded-full {{ $format ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-bold">{{ $format ? 'Tersimpan' : 'Format bawaan' }}</span></div>
                    <form method="POST" action="{{ route('document-number-formats.update', $documentType) }}" class="p-5">@csrf @method('PUT')<input type="hidden" name="document_type" value="{{ $documentType }}">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem_10rem]">
                            <label class="text-sm font-bold text-slate-700">Pola nomor<input name="format_pattern" x-model="pattern" value="{{ $pattern }}" maxlength="80" class="mt-1.5 w-full rounded-lg border-slate-300 font-mono text-sm" required></label>
                            <label class="text-sm font-bold text-slate-700">Reset urutan<select name="reset_period" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm">@foreach(['YEAR' => 'Setiap tahun', 'QUARTER' => 'Setiap triwulan', 'MONTH' => 'Setiap bulan', 'NONE' => 'Tidak pernah'] as $value => $label)<option value="{{ $value }}" @selected($reset === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="text-sm font-bold text-slate-700">Digit urutan<input name="padding" x-model="padding" type="number" min="1" max="8" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm" required></label>
                        </div>
                        @if(old('document_type') === $documentType)@error('format_pattern')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror @endif
                        <div class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pratinjau nomor pertama</p><p class="mt-1 break-all font-mono text-sm font-bold text-indigo-700" x-text="preview()">{{ $preview }}</p></div><button class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">Simpan format</button></div>
                    </form>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.tailwind-app>
