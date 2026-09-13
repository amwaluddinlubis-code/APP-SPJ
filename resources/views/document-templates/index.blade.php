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
        $canonicalTemplateCount = count($documentTypes);
        $placeholderCount = collect($placeholderGroups)->flatten()->count();
        $validationCollection = collect($validationResults ?? []);
        $validationErrorCount = $validationCollection->filter(fn($result) => !empty($result['errors']))->count();
        $validationWarningCount = $validationCollection
            ->filter(fn($result) => empty($result['errors']) && !empty($result['warnings']))
            ->count();
        $validationValidCount = $validationCollection
            ->filter(fn($result) => empty($result['errors']) && empty($result['warnings']))
            ->count();
        $packageErrors = $errors->getBag('templatePackageUpload');
        $templateErrors = $errors->getBag('templateUpload');
        $fileInputClass = 'block w-full rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm text-[var(--ui-fg)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--theme-accent-soft)] file:px-3 file:py-2 file:text-xs file:font-bold file:text-[var(--theme-content-accent)]';
    @endphp

    <div class="space-y-6">
        <x-page-header title="Template Dokumen Word dan Excel"
            subtitle="Unggah, atur, validasi, dan tentukan kategori SPJ yang menggunakan setiap template dokumen."
            kicker="PENGATURAN TEMPLATE DOKUMEN">
            <x-slot:actions>
                <x-ui.button :href="route('document-templates.master.download')">Unduh Master Template Terbaru</x-ui.button>
                <x-ui.button variant="secondary" x-on:click="$dispatch('open-placeholder-checker')">Cek Placeholder</x-ui.button>
                <x-ui.button variant="secondary" :href="route('document-templates.sample', 'docx')">Unduh Contoh Word</x-ui.button>
                <x-ui.button variant="secondary" :href="route('document-templates.sample', 'xlsx')">Unduh Contoh Excel</x-ui.button>
            </x-slot:actions>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Template tampil" :value="number_format($templates->total(), 0, ',', '.')"
                    hint="Sesuai filter yang sedang digunakan" value-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Template canonical" :value="number_format($canonicalTemplateCount, 0, ',', '.')"
                    hint="Jumlah sheet pada paket master" value-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Kategori SPJ" :value="number_format(count($categories), 0, ',', '.')"
                    hint="Kategori canonical yang dapat dihubungkan" value-class="text-emerald-700" />
                <x-stat-item label="Penanda Data" :value="number_format($placeholderCount, 0, ',', '.')"
                    hint="Penanda yang dikenal oleh engine template" value-class="text-[var(--ui-fg-strong)]" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-sm font-bold text-[var(--ui-fg-strong)]">Status batas upload server</p>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">
                        PHP upload_max_filesize = <span class="font-mono font-bold">{{ $uploadLimits['upload_max_filesize'] ?? '-' }}</span>
                        · post_max_size = <span class="font-mono font-bold">{{ $uploadLimits['post_max_size'] ?? '-' }}</span>
                        · batas efektif ≈ <span class="font-bold">{{ $uploadLimits['effective_max_upload'] ?? '-' }}</span>.
                    </p>
                </div>
                <p class="max-w-xl text-xs leading-5 text-[var(--ui-fg-muted)]">
                    Jika upload ditolak sebelum Laravel menerima file, halaman sekarang tetap mengetahui form mana yang digunakan
                    dan menampilkan pesan batas PHP pada form yang benar.
                </p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.form-section title="Import Paket Template"
                description="Untuk master XLSX yang berisi seluruh {{ count($documentTypes) }} sheet canonical. Aplikasi memvalidasi seluruh sheet lalu mendaftarkan tiap jenis dokumen sebagai template aktif. Update XLSX individu berikutnya otomatis menjadi sumber saat Master Template Terbaru dirakit.">
                <form method="POST" action="{{ route('document-templates.store', ['upload' => 'package']) }}"
                    enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <x-ui.field label="File workbook master (XLSX)" for="template_package"
                        hint="Pilih workbook master {{ $canonicalTemplateCount }} template. Maksimal 20 MB dan tetap mengikuti batas efektif server."
                        :error="$packageErrors->first('template_package')" required>
                        <input id="template_package" type="file" name="template_package" accept=".xlsx"
                            class="{{ $fileInputClass }}" required>
                    </x-ui.field>

                    <div class="rounded-xl border border-[var(--ui-line)] bg-amber-50/70 p-4">
                        <label class="flex items-start gap-3 text-sm font-semibold text-[var(--ui-fg)]">
                            <input type="hidden" name="replace_existing" value="0">
                            <input type="checkbox" name="replace_existing" value="1" @checked(old('replace_existing'))>
                            <span>
                                Ganti template yang sudah ada
                                <span class="mt-1 block text-xs font-normal text-[var(--ui-fg-muted)]">
                                    Jika tidak dicentang dan template canonical XLSX sudah tersedia, import dibatalkan agar file lama tetap aman.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Isi paket master: {{ $canonicalTemplateCount }} template canonical</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($documentTypes as $documentType => $documentLabel)
                                <span class="rounded-full bg-[var(--ui-surface-muted)] px-3 py-1 text-[11px] font-semibold text-[var(--ui-fg)]">
                                    <span class="font-mono text-[var(--theme-content-accent)]">{{ $documentType }}</span> · {{ $documentLabel }}
                                </span>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-[var(--ui-fg-muted)]">
                            Sheet teknis <span class="font-mono">PLACEHOLDER_MAP</span> hanya menjadi peta bantuan dan tidak dibuat sebagai template aplikasi.
                        </p>
                    </div>

                    <div class="ui-form-actions">
                        <x-ui.button type="submit">Validasi & Import {{ $canonicalTemplateCount }} Template</x-ui.button>
                    </div>
                </form>

                @if (session('template_package_warnings'))
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-bold text-amber-800">Paket berhasil diimpor dengan peringatan validasi.</p>
                        <ul class="mt-2 space-y-1 text-xs text-amber-800">
                            @foreach (session('template_package_warnings') as $warning)
                                <li>• {{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.form-section>

            <x-ui.form-section title="Tambah atau Ganti Satu Template"
                description="Untuk satu file DOCX/XLSX. Pilih jenis dokumen yang sesuai dengan file, kemudian simpan sebagai template aktif. Jika yang diperbarui XLSX, versi ini otomatis dipakai pada download Master Template Terbaru berikutnya.">
                <form method="POST" action="{{ route('document-templates.store', ['upload' => 'single']) }}"
                    enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.field label="Jenis Dokumen" for="document_type"
                            :error="$templateErrors->first('document_type')" required>
                            <x-ui.select id="document_type" name="document_type" required>
                                @foreach ($documentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Nama Template" for="template_name"
                            :error="$templateErrors->first('name')" required>
                            <x-ui.input id="template_name" name="name" :value="old('name')"
                                placeholder="Contoh: Kuitansi BOSP 2026" required />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="File template (DOCX/XLSX)" for="template_file"
                        hint="Maksimal 10 MB dan tetap mengikuti batas efektif server yang ditampilkan di atas."
                        :error="$templateErrors->first('template')" required>
                        <input id="template_file" type="file" name="template" accept=".docx,.xlsx"
                            class="{{ $fileInputClass }}" required>
                    </x-ui.field>

                    <fieldset class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                        <legend class="px-1 text-xs font-bold text-[var(--ui-fg)]">Digunakan untuk Kategori SPJ</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($categories as $category)
                                <label class="ui-choice-card text-xs">
                                    <input type="checkbox" name="applicable_categories[]" value="{{ $category }}"
                                        @checked(in_array($category, old('applicable_categories', []), true))>
                                    <span>{{ $labels[$category] ?? str_replace('_', ' ', $category) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-[var(--ui-fg-muted)]">
                            Jika tidak ada kategori yang dipilih, template tersedia untuk semua kategori SPJ.
                        </p>
                    </fieldset>

                    <div class="ui-form-actions">
                        <x-ui.button type="submit">Validasi & Simpan Template</x-ui.button>
                    </div>
                </form>

                @if (session('template_validation_warnings'))
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-bold text-amber-800">Template berhasil disimpan dengan peringatan validasi.</p>
                        <ul class="mt-2 space-y-1 text-xs text-amber-800">
                            @foreach (session('template_validation_warnings') as $warning)
                                <li>• {{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.form-section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                        <h2 class="font-bold text-[var(--ui-fg-strong)]">3. Hasil Validasi Template</h2>
                        <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">VALID siap digunakan. WARNING hanya perlu ditinjau dan tidak menghalangi penggunaan. ERROR harus diperbaiki.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">VALID {{ $validationValidCount }}</span>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">WARNING {{ $validationWarningCount }}</span>
                        <span class="rounded-full bg-rose-100 px-3 py-1 text-rose-800">ERROR {{ $validationErrorCount }}</span>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-[var(--ui-line)]">
                @forelse($templates as $template)
                    @php
                        $validation = $validationResults[$template->id] ?? [
                            'valid' => false,
                            'errors' => [],
                            'warnings' => [],
                            'markers' => [],
                            'sheet' => null,
                        ];
                        $hasErrors = !empty($validation['errors']);
                        $hasWarnings = !$hasErrors && !empty($validation['warnings']);
                        $validationStatus = $hasErrors ? 'ERROR' : ($hasWarnings ? 'WARNING' : 'VALID');
                        $statusClass = $hasErrors
                            ? 'bg-rose-100 text-rose-800'
                            : ($hasWarnings ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');
                    @endphp
                    <details class="group px-5 py-4 sm:px-6" @if ($hasErrors) open @endif>
                        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-[var(--ui-fg-strong)]">{{ $template->name }}</span>
                                    <span class="font-mono text-[11px] text-[var(--theme-content-accent)]">{{ $template->document_type }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $statusClass }}">{{ $validationStatus }}</span>
                                </div>
                                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">
                                    {{ strtoupper($template->format) }}
                                    @if (!empty($validation['sheet']))
                                        · Sheet: <span class="font-mono">{{ $validation['sheet'] }}</span>
                                    @endif
                                    · {{ count($validation['markers'] ?? []) }} placeholder terdeteksi
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-[var(--ui-fg-muted)] group-open:hidden">Lihat rincian</span>
                            <span class="hidden text-xs font-semibold text-[var(--ui-fg-muted)] group-open:inline">Tutup rincian</span>
                        </summary>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Error</h3>
                                @if (!empty($validation['errors']))
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($validation['errors'] as $issue)
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

                            <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Warning</h3>
                                @if (!empty($validation['warnings']))
                                    <ul class="mt-2 space-y-2">
                                        @foreach ($validation['warnings'] as $issue)
                                            <li class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                                <span class="font-mono font-bold">{{ $issue['code'] }}</span>
                                                <p class="mt-1">{{ $issue['message'] }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-2 text-xs text-[var(--ui-fg-muted)]">Tidak ada warning.</p>
                                @endif
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="px-5 py-10"><x-ui.empty-state title="Belum ada template pada filter ini untuk divalidasi." icon="document" /></div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Template yang Tersedia</h2>
                <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Download Template mengunduh dokumen terpilih saja. Gunakan Unduh Master Template Terbaru untuk merakit seluruh template XLSX aktif.</p>
            </div>
            <form method="GET"
                class="grid gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4 sm:grid-cols-[12rem_minmax(12rem,1fr)_auto_auto] sm:items-end">
                    <x-ui.field label="Tampilkan status" for="status">
                    <x-ui.select id="status" name="status">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua Status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Tidak Aktif</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Tampilkan kategori" for="category">
                    <x-ui.select id="category" name="category">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>
                                {{ $labels[$category] ?? str_replace('_', ' ', $category) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="submit">Tampilkan</x-ui.button>
                <x-ui.button variant="secondary" :href="route('document-templates.index')">Hapus Filter</x-ui.button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" data-pagination="server">
                    <thead class="bg-[var(--ui-surface-muted)]">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">Format</th>
                            <th class="px-4 py-3">Digunakan untuk</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)]">
                        @forelse($templates as $template)
                            @php
                                $canonicalType = \App\Services\SpjDocumentTypeRegistry::canonical((string) $template->document_type);
                                $isCanonical = array_key_exists((string) $template->document_type, $documentTypes);
                                $templateValidation = $validationResults[$template->id] ?? null;
                                $tableValidationStatus = !empty($templateValidation['errors'] ?? [])
                                    ? 'ERROR'
                                    : (!empty($templateValidation['warnings'] ?? []) ? 'WARNING' : 'VALID');
                            @endphp
                            <tr class="odd:bg-[var(--ui-surface-base)] even:bg-[var(--ui-surface-soft)] hover:bg-[var(--ui-table-row-hover)]">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $template->name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-[11px] text-[var(--theme-content-accent)]">{{ $template->document_type }}</span>
                                        @if (!$isCanonical)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Legacy</span>
                                        @endif
                                        <span class="text-[10px] font-bold {{ $tableValidationStatus === 'ERROR' ? 'text-rose-700' : ($tableValidationStatus === 'WARNING' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $tableValidationStatus }}</span>
                                    </div>
                                    @if (!$isCanonical && $canonicalType)
                                        <p class="mt-1 text-[11px] text-amber-700">Alias lama untuk <span class="font-mono font-semibold">{{ $canonicalType }}</span>.</p>
                                    @elseif(!$isCanonical)
                                        <p class="mt-1 text-[11px] text-[var(--ui-fg-muted)]">Belum memiliki padanan pada registry canonical.</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="rounded bg-[var(--ui-surface-muted)] px-2 py-1 text-[11px] font-bold uppercase text-[var(--ui-fg)]">{{ $template->format }}</span>
                                </td>
                                <td class="min-w-[320px] px-4 py-3 align-top">
                                    <form id="mapping-{{ $template->id }}" method="POST"
                                        action="{{ route('document-templates.mapping.update', $template->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($categories as $category)
                                                <label class="ui-choice-card text-xs">
                                                    <input type="checkbox" name="applicable_categories[]" value="{{ $category }}"
                                                        @checked(in_array($category, $template->applicable_categories ?? [], true))>
                                                    <span>{{ $labels[$category] ?? str_replace('_', ' ', $category) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </form>
                                    @if (empty($template->applicable_categories))
                                        <p class="mt-2 text-[11px] font-semibold text-emerald-700">Template ini dapat digunakan untuk semua kategori SPJ.</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center align-top">
                                    <input form="mapping-{{ $template->id }}" type="hidden" name="is_active" value="0">
                                    <label class="inline-flex items-center gap-2 text-xs font-bold {{ $template->is_active ? 'text-emerald-700' : 'text-[var(--ui-fg-muted)]' }}">
                                        <input form="mapping-{{ $template->id }}" type="checkbox" name="is_active" value="1" @checked($template->is_active)>
                                        {{ $template->is_active ? 'Aktif' : 'Tidak aktif' }}
                                    </label>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-ui.button variant="secondary" :href="route('document-templates.download', $template->id)">Download Template</x-ui.button>
                                        <x-ui.button form="mapping-{{ $template->id }}" type="submit">Simpan Perubahan</x-ui.button>
                                        <form method="POST" action="{{ route('document-templates.destroy', $template->id) }}"
                                            data-confirm="Hapus template {{ $template->name }}? Template yang dihapus tidak dapat digunakan lagi. Lanjutkan?">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="danger">Hapus</x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-[var(--ui-fg-muted)]">Tidak ada template yang sesuai dengan filter saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($templates->hasPages())
                <div class="border-t border-[var(--ui-line)] px-5 py-4">{{ $templates->links() }}</div>
            @endif
        </section>

        <details class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <summary class="cursor-pointer px-5 py-4 text-sm font-bold text-[var(--ui-fg)]">Lihat semua penanda data ({{ $placeholderCount }})</summary>
            <div class="space-y-5 border-t border-[var(--ui-line)] px-5 py-4">
                <p class="text-xs text-[var(--ui-fg-muted)]">
                    Masukkan penanda ke template dengan kurung kurawal ganda, misalnya
                    <code>&#123;&#123;NOMOR_SPJ&#125;&#125;</code>. Untuk rincian banyak baris, letakkan penanda rincian pada satu baris contoh.
                </p>
                @foreach ($placeholderGroups as $group => $markers)
                    <section>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $group }}</h3>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($markers as $marker)
                                <code class="rounded bg-[var(--ui-surface-muted)] px-2 py-1 text-[11px] text-[var(--theme-content-accent)]">&#123;&#123;{{ $marker }}&#125;&#125;</code>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </details>

        @include('document-templates.partials.placeholder-checker')
    </div>
</x-layouts.tailwind-app>
