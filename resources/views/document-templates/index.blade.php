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

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <details class="group border-b border-slate-100" @if($errors->any()) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-bold text-violet-700 hover:bg-violet-50"><span>+ Unggah atau ganti template</span><span class="transition group-open:rotate-180">⌄</span></summary>
                <form method="POST" action="{{ route('document-templates.store') }}" enctype="multipart/form-data" class="grid gap-4 border-t border-slate-100 bg-slate-50/60 p-5 lg:grid-cols-2">@csrf
                    <div><label for="document_type" class="text-xs font-bold text-slate-700">Jenis Dokumen *</label><select id="document_type" name="document_type" class="mt-1 form-select w-full" required>@foreach(['KUITANSI'=>'Kuitansi','RINCIAN_BELANJA'=>'Rincian Belanja','DAFTAR_PEMBAYARAN'=>'Daftar Pembayaran','DAFTAR_HADIR'=>'Daftar Hadir','CHECKLIST'=>'Checklist SPJ','REKAP_PAJAK'=>'Rekap Pajak','INVOICE_PESANAN'=>'Pesanan/Invoice','BAP'=>'BAP','BAST'=>'BAST','SPK'=>'SPK','SURAT_TUGAS'=>'Surat Tugas','SPPD'=>'SPPD'] as $value=>$label)<option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="template_name" class="text-xs font-bold text-slate-700">Nama Template *</label><input id="template_name" name="name" value="{{ old('name') }}" class="mt-1 form-control w-full" placeholder="Contoh: Kuitansi BOSP 2026" required></div>
                    <div><label for="template_file" class="text-xs font-bold text-slate-700">Berkas Template *</label><input id="template_file" type="file" name="template" accept=".docx,.xlsx" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm" required><p class="mt-1 text-[11px] text-slate-500">DOCX atau XLSX, maksimum 10 MB.</p></div>
                    <fieldset><legend class="text-xs font-bold text-slate-700">Kategori SPJ</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($categories as $category)<label class="flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category, old('applicable_categories', []), true)) class="rounded border-slate-300 text-violet-600">{{ $labels[$category] ?? str_replace('_',' ',$category) }}</label>@endforeach</div><p class="mt-2 text-[11px] text-slate-500">Kosong berarti berlaku untuk semua kategori.</p></fieldset>
                    <div class="lg:col-span-2"><button class="rounded-md bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">Simpan Template</button></div>
                </form>
            </details>

            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-bold text-slate-800">Daftar Template Dokumen</h2>
                <p class="mt-1 text-sm text-slate-500">Atur status dan kategori SPJ untuk setiap template.</p>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
                <div><label for="status" class="text-[11px] font-bold uppercase text-slate-500">Status</label><select id="status" name="status" class="mt-1 form-select min-w-36"><option value="all" @selected(($filters['status'] ?? 'all')==='all')>Semua</option><option value="active" @selected(($filters['status'] ?? '')==='active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '')==='inactive')>Tidak Aktif</option></select></div>
                <div><label for="category" class="text-[11px] font-bold uppercase text-slate-500">Kategori SPJ</label><select id="category" name="category" class="mt-1 form-select min-w-48"><option value="">Semua Kategori</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '')===$category)>{{ $labels[$category] ?? str_replace('_',' ',$category) }}</option>@endforeach</select></div>
                <button class="rounded-md bg-slate-800 px-4 py-2.5 text-sm font-bold text-white">Terapkan</button><a href="{{ route('document-templates.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700">Reset</a>
            </form>

            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-100"><tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-600"><th class="px-4 py-3">Template</th><th class="px-4 py-3">Format</th><th class="px-4 py-3">Kategori SPJ</th><th class="px-4 py-3 text-center">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($templates as $template)
                    <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-violet-50/60">
                        <td class="px-4 py-3 align-top"><p class="font-semibold text-slate-800">{{ $template->name }}</p><p class="mt-1 font-mono text-[11px] text-violet-700">{{ $template->document_type }}</p></td>
                        <td class="px-4 py-3 align-top"><span class="rounded bg-slate-200 px-2 py-1 text-[11px] font-bold uppercase text-slate-700">{{ $template->format }}</span></td>
                        <td class="min-w-[320px] px-4 py-3 align-top"><form id="mapping-{{ $template->id }}" method="POST" action="{{ route('document-templates.mapping.update',$template->id) }}">@csrf @method('PUT')<div class="grid gap-1.5 sm:grid-cols-2">@foreach($categories as $category)<label class="flex items-center gap-1.5 text-xs text-slate-700"><input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category,$template->applicable_categories ?? [],true)) class="rounded border-slate-300 text-violet-600">{{ $labels[$category] ?? str_replace('_',' ',$category) }}</label>@endforeach</div></form>@if(empty($template->applicable_categories))<p class="mt-2 text-[11px] font-semibold text-emerald-700">Berlaku untuk semua kategori</p>@endif</td>
                        <td class="px-4 py-3 text-center align-top"><input form="mapping-{{ $template->id }}" type="hidden" name="is_active" value="0"><label class="inline-flex items-center gap-2 text-xs font-bold {{ $template->is_active ? 'text-emerald-700':'text-slate-500' }}"><input form="mapping-{{ $template->id }}" type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-slate-300 text-emerald-600">{{ $template->is_active ? 'Aktif':'Nonaktif' }}</label></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right align-top"><button form="mapping-{{ $template->id }}" class="rounded-md bg-violet-600 px-3 py-2 text-xs font-bold text-white">Simpan</button><form class="ml-1 inline" method="POST" action="{{ route('document-templates.destroy',$template->id) }}" data-confirm="Hapus template {{ $template->name }}?">@csrf @method('DELETE')<button class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Hapus</button></form></td>
                    </tr>
                @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada template yang sesuai.</td></tr>@endforelse
            </tbody></table></div>
            @if($templates->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $templates->links() }}</div>@endif
        </section>

        <details class="rounded-xl border border-slate-200 bg-white shadow-sm"><summary class="cursor-pointer px-5 py-4 text-sm font-bold text-slate-700">Semua penanda yang dapat digunakan ({{ $placeholderCount }})</summary><div class="space-y-5 border-t border-slate-100 px-5 py-4"><p class="text-xs text-slate-500">Gunakan kurung kurawal ganda, misalnya <code>&#123;&#123;NOMOR_SPJ&#125;&#125;</code>. Penanda pada kelompok baris rincian harus ditempatkan pada satu baris tabel contoh agar baris dapat digandakan otomatis.</p>@foreach($placeholderGroups as $group => $markers)<section><h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $group }}</h3><div class="mt-2 flex flex-wrap gap-1.5">@foreach($markers as $marker)<code class="rounded bg-slate-100 px-2 py-1 text-[11px] text-indigo-700">&#123;&#123;{{ $marker }}&#125;&#125;</code>@endforeach</div></section>@endforeach</div></details>
    </div>
</x-layouts.tailwind-app>
