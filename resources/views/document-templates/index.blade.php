<x-layouts.tailwind-app>
    @php
        $labels = [
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
            'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
        ];
        $placeholderCount = collect($placeholderGroups)->flatten()->count();
        $validationCollection = collect($validationResults ?? []);
        $validationErrorCount = $validationCollection->filter(fn ($result) => ! empty($result['errors']))->count();
        $validationWarningCount = $validationCollection->filter(fn ($result) => empty($result['errors']) && ! empty($result['warnings']))->count();
        $validationValidCount = $validationCollection->filter(fn ($result) => empty($result['errors']) && empty($result['warnings']))->count();
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Template Dokumen Word dan Excel"
            subtitle="Unggah, atur, validasi, dan tentukan kategori SPJ yang menggunakan setiap template dokumen."
            kicker="PENGATURAN TEMPLATE DOKUMEN"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('document-templates.sample', 'docx')">Unduh Contoh Word</x-ui.button>
                <x-ui.button variant="secondary" :href="route('document-templates.sample', 'xlsx')">Unduh Contoh Excel</x-ui.button>
            </x-slot:actions>
            <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Jumlah Template" :value="number_format($templates->total(), 0, ',', '.')" hint="Sesuai filter yang sedang digunakan" value-class="text-indigo-700" />
                <x-stat-item label="Kategori SPJ" :value="number_format(count($categories), 0, ',', '.')" hint="Kategori canonical yang dapat dihubungkan" value-class="text-emerald-700" />
                <x-stat-item label="Penanda Data" :value="number_format($placeholderCount, 0, ',', '.')" hint="Penanda yang dikenal oleh engine template" value-class="text-slate-800" />
            </div>
        </x-page-header>

        <x-ui.form-section title="Tambah atau Ganti Template" description="Pilih jenis dokumen canonical, unggah file DOCX/XLSX, lalu tentukan kategori SPJ. File diperiksa terhadap kontrak placeholder sebelum disimpan.">
            <form method="POST" action="{{ route('document-templates.store') }}" enctype="multipart/form-data" class="space-y-5">@csrf
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.field label="Jenis Dokumen" for="document_type" :error="$errors->first('document_type')" required>
                        <x-ui.select id="document_type" name="document_type" required>
                            @foreach($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="Nama Template" for="template_name" :error="$errors->first('name')" required>
                        <x-ui.input id="template_name" name="name" :value="old('name')" placeholder="Contoh: Kuitansi BOSP 2026" required />
                    </x-ui.field>
                    <x-ui.field label="File Template" for="template_file" hint="DOCX/XLSX maksimal 10 MB. ERROR akan menolak upload; WARNING tetap dapat disimpan." :error="$errors->first('template')" required>
                        <input id="template_file" type="file" name="template" accept=".docx,.xlsx" required>
                    </x-ui.field>
                    <fieldset class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <legend class="px-1 text-xs font-bold text-slate-700">Digunakan untuk Kategori SPJ</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach($categories as $category)
                                <label class="ui-choice-card text-xs">
                                    <input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category, old('applicable_categories', []), true))>
                                    <span>{{ $labels[$category] ?? str_replace('_', ' ', $category) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Jika tidak ada kategori yang dipilih, template akan tersedia untuk semua kategori SPJ.</p>
                    </fieldset>
                </div>
                <div class="ui-form-actions"><x-ui.button type="submit">Validasi & Simpan Template</x-ui.button></div>
            </form>

            @if(session('template_validation_warnings'))
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-800">Template berhasil disimpan dengan peringatan validasi.</p>
                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                        @foreach(session('template_validation_warnings') as $warning)
                            <li>• {{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-ui.form-section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800">Hasil Validasi Template</h2>
                        <p class="mt-1 text-sm text-slate-500">Pemeriksaan dilakukan pada template yang tampil di halaman ini. ERROR perlu diperbaiki; WARNING tidak memblokir penggunaan.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">VALID {{ $validationValidCount }}</span>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">WARNING {{ $validationWarningCount }}</span>
                        <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800">ERROR {{ $validationErrorCount }}</span>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($templates as $template)
                    @php
                        $validation = $validationResults[$template->id] ?? ['valid' => false, 'errors' => [], 'warnings' => [], 'markers' => [], 'sheet' => null];
                        $hasErrors = ! empty($validation['errors']);
                        $hasWarnings = ! $hasErrors && ! empty($validation['warnings']);
                        $validationStatus = $hasErrors ? 'ERROR' : ($hasWarnings ? 'WARNING' : 'VALID');
                        $statusClass = $hasErrors
                            ? 'bg-rose-100 text-rose-800'
                            : ($hasWarnings ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');
                    @endphp
                    <details class="group px-5 py-4 sm:px-6" @if($hasErrors) open @endif>
                        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-slate-800">{{ $template->name }}</span>
                                    <span class="font-mono text-[11px] text-violet-700">{{ $template->document_type }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $statusClass }}">{{ $validationStatus }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ strtoupper($template->format) }}
                                    @if(! empty($validation['sheet'])) · Sheet: <span class="font-mono">{{ $validation['sheet'] }}</span>@endif
                                    · {{ count($validation['markers'] ?? []) }} placeholder terdeteksi
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 group-open:hidden">Lihat rincian</span>
                            <span class="hidden text-xs font-semibold text-slate-500 group-open:inline">Tutup rincian</span>
                        </summary>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">Error</h3>
                                @if(! empty($validation['errors']))
                                    <ul class="mt-2 space-y-2">
                                        @foreach($validation['errors'] as $issue)
                                            <li class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                                                <span class="font-mono font-bold">{{ $issue['code'] }}</span>
                                                <p class="mt-1">{{ $issue['message'] }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-xs font-semibold text-emerald-700">Tidak ada error.</p>
                                @endif
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">Warning</h3>
                                @if(! empty($validation['warnings']))
                                    <ul class="mt-2 space-y-2">
                                        @foreach($validation['warnings'] as $issue)
                                            <li class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                                <span class="font-mono font-bold">{{ $issue['code'] }}</span>
                                                <p class="mt-1">{{ $issue['message'] }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-xs text-slate-500">Tidak ada warning.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">Belum ada template pada filter ini untuk divalidasi.</div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-bold text-slate-800">Template yang Tersedia</h2>
                <p class="mt-1 text-sm text-slate-500">Template baru memakai document type dan kategori canonical. Unduh template terakhir sebelum menggantinya jika perlu melakukan revisi lokal.</p>
            </div>
            <form method="GET" class="grid gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto_auto] sm:items-end">
                <x-ui.field label="Status Template" for="status">
                    <x-ui.select id="status" name="status">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua Status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak Aktif</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Kategori SPJ" for="category">
                    <x-ui.select id="category" name="category">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $labels[$category] ?? str_replace('_', ' ', $category) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="submit">Tampilkan</x-ui.button>
                <x-ui.button variant="secondary" :href="route('document-templates.index')">Hapus Filter</x-ui.button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" data-pagination="server">
                    <thead class="bg-slate-100">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-600">
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">Format</th>
                            <th class="px-4 py-3">Digunakan untuk</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($templates as $template)
                            @php
                                $canonicalType = \App\Services\SpjDocumentTypeRegistry::canonical((string) $template->document_type);
                                $isCanonical = array_key_exists((string) $template->document_type, $documentTypes);
                                $templateValidation = $validationResults[$template->id] ?? null;
                                $tableValidationStatus = ! empty($templateValidation['errors'] ?? []) ? 'ERROR' : (! empty($templateValidation['warnings'] ?? []) ? 'WARNING' : 'VALID');
                            @endphp
                            <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-violet-50/60">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold text-slate-800">{{ $template->name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-[11px] text-violet-700">{{ $template->document_type }}</span>
                                        @if(! $isCanonical)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Legacy</span>
                                        @endif
                                        <span class="text-[10px] font-bold {{ $tableValidationStatus === 'ERROR' ? 'text-rose-700' : ($tableValidationStatus === 'WARNING' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $tableValidationStatus }}</span>
                                    </div>
                                    @if(! $isCanonical && $canonicalType)
                                        <p class="mt-1 text-[11px] text-amber-700">Alias lama untuk <span class="font-mono font-semibold">{{ $canonicalType }}</span>. Jika terjadi benturan dengan template canonical, data legacy dipertahankan dalam keadaan nonaktif.</p>
                                    @elseif(! $isCanonical)
                                        <p class="mt-1 text-[11px] text-slate-500">Belum memiliki padanan pada registry document type canonical v1.</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top"><span class="rounded bg-slate-200 px-2 py-1 text-[11px] font-bold uppercase text-slate-700">{{ $template->format }}</span></td>
                                <td class="min-w-[320px] px-4 py-3 align-top">
                                    <form id="mapping-{{ $template->id }}" method="POST" action="{{ route('document-templates.mapping.update', $template->id) }}">@csrf @method('PUT')
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach($categories as $category)
                                                <label class="ui-choice-card text-xs">
                                                    <input type="checkbox" name="applicable_categories[]" value="{{ $category }}" @checked(in_array($category, $template->applicable_categories ?? [], true))>
                                                    <span>{{ $labels[$category] ?? str_replace('_', ' ', $category) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </form>
                                    @if(empty($template->applicable_categories))
                                        <p class="mt-2 text-[11px] font-semibold text-emerald-700">Template ini dapat digunakan untuk semua kategori SPJ.</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center align-top">
                                    <input form="mapping-{{ $template->id }}" type="hidden" name="is_active" value="0">
                                    <label class="inline-flex items-center gap-2 text-xs font-bold {{ $template->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                                        <input form="mapping-{{ $template->id }}" type="checkbox" name="is_active" value="1" @checked($template->is_active)>
                                        {{ $template->is_active ? 'Aktif' : 'Tidak aktif' }}
                                    </label>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-ui.button variant="secondary" :href="route('document-templates.download', $template->id)">Download Template</x-ui.button>
                                        <x-ui.button form="mapping-{{ $template->id }}" type="submit">Simpan Perubahan</x-ui.button>
                                        <form method="POST" action="{{ route('document-templates.destroy', $template->id) }}" data-confirm="Hapus template {{ $template->name }}? Template yang dihapus tidak dapat digunakan lagi. Lanjutkan?">@csrf @method('DELETE')
                                            <x-ui.button type="submit" variant="danger">Hapus</x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Tidak ada template yang sesuai dengan filter saat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($templates->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">{{ $templates->links() }}</div>
            @endif
        </section>

        <details class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <summary class="cursor-pointer px-5 py-4 text-sm font-bold text-slate-700">Lihat semua penanda data ({{ $placeholderCount }})</summary>
            <div class="space-y-5 border-t border-slate-100 px-5 py-4">
                <p class="text-xs text-slate-500">Masukkan penanda ke template dengan kurung kurawal ganda, misalnya <code>&#123;&#123;NOMOR_SPJ&#125;&#125;</code>. Untuk rincian yang memiliki banyak baris, letakkan penanda rincian pada satu baris contoh. Aplikasi akan menggandakan baris tersebut sesuai jumlah data.</p>
                @foreach($placeholderGroups as $group => $markers)
                    <section>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $group }}</h3>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($markers as $marker)
                                <code class="rounded bg-slate-100 px-2 py-1 text-[11px] text-indigo-700">&#123;&#123;{{ $marker }}&#125;&#125;</code>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </details>
    </div>
</x-layouts.tailwind-app>
