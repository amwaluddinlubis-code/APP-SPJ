<x-layouts.tailwind-app>
    @php
        $labels = ['BARANG'=>'Barang','BELANJA_MODAL'=>'Belanja Modal','KONSUMSI'=>'Konsumsi','JASA_HONORARIUM'=>'Jasa/Honorarium','HONOR_PEGAWAI'=>'Honor Pegawai','UPAH'=>'Upah','PEMELIHARAAN'=>'Pemeliharaan','JASA'=>'Jasa','PERJALANAN_DINAS'=>'Perjalanan Dinas','LAINNYA'=>'Lainnya'];
        $placeholderCount = collect($placeholderGroups)->flatten()->count();
    @endphp
    <div class="space-y-6">
        <x-page-header
            title="Template Word dan Excel"
            subtitle="Kelola berkas template dan kategori SPJ yang menggunakan setiap template dokumen."
            kicker="Pengaturan Dokumen"
        >
            <x-slot:actions>
                <a href="{{ route('document-templates.sample', 'docx') }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/20">Contoh Word</a>
                <a href="{{ route('document-templates.sample', 'xlsx') }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/20">Contoh Excel</a>
            </x-slot:actions>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Template" :value="number_format($templates->total(), 0, ',', '.')" hint="Sesuai filter saat ini" value-class="text-indigo-700" />
                <x-stat-item label="Kategori SPJ" :value="number_format(count($categories), 0, ',', '.')" hint="Kategori yang dapat dipetakan" value-class="text-emerald-700" />
                <x-stat-item label="Penanda Data" :value="number_format($placeholderCount, 0, ',', '.')" hint="Placeholder tersedia" value-class="text-slate-800" />
            </div>
        </x-page-header>

        <x-ui.form-section title="Unggah atau Ganti Template" description="Tambahkan template DOCX/XLSX dan tentukan kategori SPJ yang menggunakannya.">
            <form method="POST" action="{{ route('document-templates.store') }}" enctype="multipart/form-data" class="space-y-5">@csrf
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.field label="Jenis Dokumen" for="document_type" :error="$errors->first('document_type')" required><x-ui.select id="document_type" name="document_type" required>@foreach(['KUITANSI'=>'Kuitansi','RINCIAN_BELANJA'=>'Rincian Belanja','DAFTAR_PEMBAYARAN'=>'Daftar Pembayaran','DAFTAR_HADIR'=>'Daftar Hadir','CHECKLIST'=>'Checklist SPJ','REKAP_PAJAK'=>'Rekap Pajak','INVOICE_PESANAN'=>'Pesanan/Invoice','BAP'=>'BAP','BAST'=>'BAST','SPK'=>'SPK','SURAT_TUGAS'=>'Surat Tugas','SPPD'=>'SPPD'] as $value=>$label)<option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
                    <x-ui.field label="Nama Template" for="template_name" :error="$errors->first('name')" required><x-ui.input id="template_name" name="name" :value="old('name')" placeholder="Contoh: Kuitansi BOSP 2026" required /></x-ui.field>
                    <x-ui.field label="Berkas Template" for="template_file" hint="DOCX atau XLSX, maksimum 10 MB." :error="$errors->first('template')" required><input id="template_file" type="file" name="template" accept=".docx,.xlsx" required></x-ui.field>
                    <fieldset class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"><legend class="px-1 text-xs font-bold text-slate-700">Kategori SPJ</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($categories as $category)<label class="ui-choice-card text-xs"><input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category, old('applicable_categories', []), true))><span>{{ $labels[$category] ?? str_replace('_',' ',$category) }}</span></label>@endforeach</div><p class="mt-3 text-xs text-slate-500">Kosong berarti berlaku untuk semua kategori.</p></fieldset>
                </div>
                <div class="ui-form-actions"><x-ui.button type="submit">Simpan Template</x-ui.button></div>
            </form>
        </x-ui.form-section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><h2 class="font-bold text-slate-800">Daftar Template Dokumen</h2><p class="mt-1 text-sm text-slate-500">Atur status dan kategori SPJ untuk setiap template.</p></div>
            <form method="GET" class="grid gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto_auto] sm:items-end">
                <x-ui.field label="Status" for="status"><x-ui.select id="status" name="status"><option value="all" @selected(($filters['status'] ?? 'all')==='all')>Semua</option><option value="active" @selected(($filters['status'] ?? '')==='active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '')==='inactive')>Tidak Aktif</option></x-ui.select></x-ui.field>
                <x-ui.field label="Kategori SPJ" for="category"><x-ui.select id="category" name="category"><option value="">Semua Kategori</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '')===$category)>{{ $labels[$category] ?? str_replace('_',' ',$category) }}</option>@endforeach</x-ui.select></x-ui.field>
                <x-ui.button type="submit">Terapkan</x-ui.button><x-ui.button variant="secondary" :href="route('document-templates.index')">Reset</x-ui.button>
            </form>

            <div class="overflow-x-auto"><table class="min-w-full text-sm" data-pagination="server"><thead class="bg-slate-100"><tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-600"><th class="px-4 py-3">Template</th><th class="px-4 py-3">Format</th><th class="px-4 py-3">Kategori SPJ</th><th class="px-4 py-3 text-center">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($templates as $template)
                    <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-violet-50/60">
                        <td class="px-4 py-3 align-top"><p class="font-semibold text-slate-800">{{ $template->name }}</p><p class="mt-1 font-mono text-[11px] text-violet-700">{{ $template->document_type }}</p></td>
                        <td class="px-4 py-3 align-top"><span class="rounded bg-slate-200 px-2 py-1 text-[11px] font-bold uppercase text-slate-700">{{ $template->format }}</span></td>
                        <td class="min-w-[320px] px-4 py-3 align-top"><form id="mapping-{{ $template->id }}" method="POST" action="{{ route('document-templates.mapping.update',$template->id) }}">@csrf @method('PUT')<div class="grid gap-2 sm:grid-cols-2">@foreach($categories as $category)<label class="ui-choice-card text-xs"><input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category,$template->applicable_categories ?? [],true))><span>{{ $labels[$category] ?? str_replace('_',' ',$category) }}</span></label>@endforeach</div></form>@if(empty($template->applicable_categories))<p class="mt-2 text-[11px] font-semibold text-emerald-700">Berlaku untuk semua kategori</p>@endif</td>
                        <td class="px-4 py-3 text-center align-top"><input form="mapping-{{ $template->id }}" type="hidden" name="is_active" value="0"><label class="inline-flex items-center gap-2 text-xs font-bold {{ $template->is_active ? 'text-emerald-700':'text-slate-500' }}"><input form="mapping-{{ $template->id }}" type="checkbox" name="is_active" value="1" @checked($template->is_active)>{{ $template->is_active ? 'Aktif':'Nonaktif' }}</label></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-top"><div class="flex justify-end gap-2"><x-ui.button form="mapping-{{ $template->id }}" type="submit">Simpan</x-ui.button><form method="POST" action="{{ route('document-templates.destroy',$template->id) }}" data-confirm="Hapus template {{ $template->name }}?">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Hapus</x-ui.button></form></div></td>
                    </tr>
                @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada template yang sesuai.</td></tr>@endforelse
            </tbody></table></div>
            @if($templates->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $templates->links() }}</div>@endif
        </section>

        <details class="rounded-xl border border-slate-200 bg-white shadow-sm"><summary class="cursor-pointer px-5 py-4 text-sm font-bold text-slate-700">Semua penanda yang dapat digunakan ({{ $placeholderCount }})</summary><div class="space-y-5 border-t border-slate-100 px-5 py-4"><p class="text-xs text-slate-500">Gunakan kurung kurawal ganda, misalnya <code>&#123;&#123;NOMOR_SPJ&#125;&#125;</code>. Penanda pada kelompok baris rincian harus ditempatkan pada satu baris tabel contoh agar baris dapat digandakan otomatis.</p>@foreach($placeholderGroups as $group => $markers)<section><h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $group }}</h3><div class="mt-2 flex flex-wrap gap-1.5">@foreach($markers as $marker)<code class="rounded bg-slate-100 px-2 py-1 text-[11px] text-indigo-700">&#123;&#123;{{ $marker }}&#125;&#125;</code>@endforeach</div></section>@endforeach</div></details>
    </div>
</x-layouts.tailwind-app>
