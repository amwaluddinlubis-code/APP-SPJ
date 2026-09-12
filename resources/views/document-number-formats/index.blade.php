<x-layouts.tailwind-app>
    @php($labels = $documentLabels)
    @php($romanMonth = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month])
    @php($quarterRoman = [1 => 'I', 'II', 'III', 'IV'][(int) ceil(now()->month / 3)])
    @php($tw = $quarterRoman)

    <div class="space-y-6">
        <x-page-header
            title="Format Penomoran SPJ"
            subtitle="Atur susunan nomor untuk setiap jenis dokumen pada tahun {{ $year->year }}. Perubahan hanya berlaku untuk nomor yang belum diterbitkan."
            kicker="Pengaturan Dokumen"
        >
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                <x-stat-item label="Tahun Aktif" :value="$year->year" hint="Konteks penomoran" />
                <x-stat-item label="Kode Sekolah" :value="$school->school_code ?: $school->npsn" hint="Placeholder {SCHOOL}" value-class="text-indigo-700" />
                <x-stat-item label="NPSN" :value="$school->npsn" hint="Placeholder {NPSN}" value-class="text-slate-800" />
                <x-stat-item label="Hak Akses" value="Admin & Operator" hint="Dapat mengubah format" value-class="text-emerald-700" />
            </div>
        </x-page-header>

        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
            <h2 class="font-bold">Placeholder yang tersedia</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($placeholders as $placeholder)
                    <code class="rounded-md bg-[var(--ui-surface-base)] px-2.5 py-1 font-bold text-indigo-700 ring-1 ring-sky-200">&#123;{{ $placeholder }}&#125;</code>
                @endforeach
            </div>
            <p class="mt-3 text-xs leading-5 text-sky-800"><strong>{SEQ}</strong> wajib ada. <strong>{SCHOOL}</strong> memakai Kode Sekolah, <strong>{NPSN}</strong> memakai NPSN, dan <strong>{TW}</strong> menghasilkan <code>I</code> sampai <code>IV</code> berdasarkan tanggal dokumen. Jika ingin menampilkan prefix, tulis literal <code>TW.{TW}</code> pada pola. Contoh tanpa prefix: <code>{SEQ}/OP/{SCHOOL}/{TW}/{YEAR}</code>.</p>
        </section>

        <div x-data class="sticky top-[58px] z-20 flex flex-col gap-3 rounded-xl border border-[var(--ui-line)] bg-[color-mix(in_srgb,var(--ui-surface-base)_94%,transparent)] p-3 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-bold text-[var(--ui-fg-strong)]">{{ $documentTypes->count() }} jenis dokumen</p>
                <p class="text-xs text-[var(--ui-fg-muted)]">Simpan tanpa reload. Buka hanya panel yang sedang dikerjakan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="secondary" @click="$dispatch('number-formats-collapse')">Tutup semua</x-ui.button>
                <x-ui.button type="button" variant="secondary" @click="$dispatch('number-formats-expand')">Buka semua</x-ui.button>
            </div>
        </div>

        <div class="space-y-3">
            @foreach($documentTypes as $documentType)
                @php($format = $formats->get($documentType))
                @php($pattern = old('document_type') === $documentType ? old('format_pattern') : ($format?->format_pattern ?? '{SEQ}/'.$documentType.'/{SCHOOL}/{TW}/{YEAR}'))
                @php($padding = old('document_type') === $documentType ? old('padding') : ($format?->padding ?? 4))
                @php($reset = old('document_type') === $documentType ? old('reset_period') : ($format?->reset_period ?? 'YEAR'))
                @php($preview = strtr($pattern, ['{SEQ}' => str_pad('1', (int) $padding, '0', STR_PAD_LEFT), '{TYPE}' => $documentType, '{SCHOOL}' => $school->school_code ?: $school->npsn, '{NPSN}' => $school->npsn, '{YEAR}' => (string) $year->year, '{MONTH}' => now()->format('m'), '{ROMAN_MONTH}' => $romanMonth, '{TW}' => $tw]))
                @php($panelStorageKey = 'spj-number-format-panel-'.$year->id.'-'.$documentType)

                <article
                    class="overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm"
                    x-data="{
                        open: @js(old('document_type') === $documentType),
                        saved: @js((bool) $format),
                        dirty: false,
                        saving: false,
                        message: '',
                        error: '',
                        pattern: @js($pattern),
                        padding: {{ (int) $padding }},
                        type: @js($documentType),
                        school: @js($school->school_code ?: $school->npsn),
                        npsn: @js($school->npsn),
                        year: @js((string) $year->year),
                        month: @js(now()->format('m')),
                        romanMonth: @js($romanMonth),
                        tw: @js($tw),
                        preview() {
                            return this.pattern
                                .replaceAll('{SEQ}', '1'.padStart(Number(this.padding), '0'))
                                .replaceAll('{TYPE}', this.type)
                                .replaceAll('{SCHOOL}', this.school)
                                .replaceAll('{NPSN}', this.npsn)
                                .replaceAll('{YEAR}', this.year)
                                .replaceAll('{MONTH}', this.month)
                                .replaceAll('{ROMAN_MONTH}', this.romanMonth)
                                .replaceAll('{TW}', this.tw)
                        },
                        markDirty() {
                            this.dirty = true
                            this.message = ''
                            this.error = ''
                        },
                        async save(form) {
                            if (this.saving) return
                            this.saving = true
                            this.message = ''
                            this.error = ''

                            try {
                                const response = await fetch(form.action, {
                                    method: 'POST',
                                    body: new FormData(form),
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                })
                                const payload = await response.json().catch(() => ({}))

                                if (!response.ok) {
                                    if (response.status === 422 && payload.errors) {
                                        this.error = Object.values(payload.errors).flat()[0] ?? 'Format belum dapat disimpan.'
                                    } else {
                                        this.error = payload.message ?? 'Format belum dapat disimpan.'
                                    }
                                    return
                                }

                                this.saved = true
                                this.dirty = false
                                this.message = payload.message ?? 'Format berhasil disimpan.'
                                window.setTimeout(() => { this.message = '' }, 3000)
                            } catch (error) {
                                this.error = 'Gagal menghubungi aplikasi. Coba simpan kembali.'
                            } finally {
                                this.saving = false
                            }
                        }
                    }"
                    x-init="
                        const stored = localStorage.getItem(@js($panelStorageKey));
                        if (stored !== null) open = stored === 'true';
                        $watch('open', value => localStorage.setItem(@js($panelStorageKey), value ? 'true' : 'false'))
                    "
                    @number-formats-expand.window="open = true"
                    @number-formats-collapse.window="open = false"
                >
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-controls="number-format-panel-{{ strtolower($documentType) }}"
                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-[var(--ui-surface-soft)] sm:px-5"
                    >
                        <div class="min-w-0">
                            <div class="flex min-w-0 items-center gap-2">
                                <p class="shrink-0 text-xs font-bold uppercase tracking-wide text-indigo-600">{{ $documentType }}</p>
                                <span class="truncate text-xs text-[var(--ui-fg-muted)]">{{ $labels[$documentType] ?? str_replace('_', ' ', $documentType) }}</span>
                            </div>
                            <p class="mt-1 truncate font-mono text-xs font-semibold text-[var(--ui-fg)]" x-text="preview()">{{ $preview }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                class="hidden rounded-full px-2.5 py-1 text-xs font-bold sm:inline-flex"
                                :class="saving ? 'bg-sky-50 text-sky-700' : (dirty ? 'bg-amber-50 text-amber-700' : (saved ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'))"
                                x-text="saving ? 'Menyimpan…' : (dirty ? 'Belum disimpan' : (saved ? 'Tersimpan' : 'Format bawaan'))"
                            ></span>
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]" aria-hidden="true">
                                <x-ui.icon name="chevron-down" size="sm" class="transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                            </span>
                        </div>
                    </button>

                    <form
                        id="number-format-panel-{{ strtolower($documentType) }}"
                        x-show="open"
                        x-collapse
                        @input="markDirty()"
                        @change="markDirty()"
                        @submit.prevent="save($event.currentTarget)"
                        method="POST"
                        action="{{ route('document-number-formats.update', $documentType) }}"
                        class="border-t border-[var(--ui-line)] p-4 sm:p-5"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="document_type" value="{{ $documentType }}">

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem_10rem]">
                            <x-ui.field label="Pola nomor" :error="old('document_type') === $documentType ? $errors->first('format_pattern') : null" required>
                                <x-ui.input name="format_pattern" x-model="pattern" :value="$pattern" maxlength="80" class="font-mono" required />
                            </x-ui.field>
                            <x-ui.field label="Reset urutan" hint="Kapan urutan kembali ke awal.">
                                <x-ui.select name="reset_period">
                                    @foreach(['YEAR' => 'Setiap tahun', 'QUARTER' => 'Setiap triwulan', 'MONTH' => 'Setiap bulan', 'NONE' => 'Tidak pernah'] as $value => $label)
                                        <option value="{{ $value }}" @selected($reset === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field label="Digit urutan" hint="Jumlah digit nomor urut." required>
                                <x-ui.input name="padding" x-model="padding" type="number" min="1" max="8" :value="$padding" required />
                            </x-ui.field>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pratinjau nomor pertama</p>
                                <p class="mt-1 break-all font-mono text-sm font-bold text-indigo-700" x-text="preview()">{{ $preview }}</p>
                                <p x-show="message" x-cloak class="mt-1 text-xs font-semibold text-emerald-700" x-text="message"></p>
                                <p x-show="error" x-cloak class="mt-1 text-xs font-semibold text-rose-600" x-text="error"></p>
                            </div>
                            <x-ui.button type="submit" x-bind:disabled="saving || (saved && !dirty)" class="shrink-0 disabled:cursor-default disabled:opacity-50">
                                <span x-show="!saving && (!saved || dirty)">Simpan Format</span>
                                <span x-show="!saving && saved && !dirty">Tersimpan</span>
                                <span x-show="saving" x-cloak>Menyimpan…</span>
                            </x-ui.button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.tailwind-app>
