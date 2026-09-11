<x-layouts.tailwind-app title="Detail Siswa">
    @php($mask = fn ($value) => $value ? '••••'.substr($value, -4) : '—')

    <div class="space-y-6">
        <nav class="text-sm text-[var(--ui-fg-muted)]" aria-label="Breadcrumb">
            <a href="{{ route('students.index') }}" class="font-medium text-[var(--theme-content-accent)] hover:underline">Siswa</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span>{{ $student->name }}</span>
        </nav>

        <x-page-header
            :title="$student->name"
            :subtitle="$student->class_name ?: 'Belum memiliki rombel'"
            :kicker="$student->source_type.' · '.($student->is_active ? 'Aktif' : 'Tidak aktif')"
        >
            <x-slot:actions>
                <x-ui.button :href="route('students.edit', $student)">Ubah</x-ui.button>
            </x-slot:actions>
        </x-page-header>

        <x-ui.form-section title="Identitas lengkap" description="Ringkasan identitas, keluarga, dan data pendaftaran siswa.">
            <x-ui.detail-list class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['NISN', $student->nisn],
                    ['NIPD', $student->nipd],
                    ['NIK', $mask($student->nik)],
                    ['Jenis kelamin', $student->gender],
                    ['Tempat lahir', $student->birth_place],
                    ['Tanggal lahir', $student->birth_date?->translatedFormat('d F Y')],
                    ['Agama', $student->religion],
                    ['Alamat', $student->address],
                    ['Telepon', $mask($student->phone)],
                    ['Email', $student->email],
                    ['Ayah', $student->father_name],
                    ['Ibu', $student->mother_name],
                    ['Wali', $student->guardian_name],
                    ['Rombel', $student->class_name],
                    ['Tingkat', $student->grade_level],
                    ['Jenis pendaftaran', $student->registration_type],
                    ['Sekolah asal', $student->previous_school],
                    ['Tanggal masuk', $student->school_entry_date?->translatedFormat('d F Y')],
                    ['Anak ke', $student->child_order],
                    ['Tinggi', $student->height ? $student->height.' cm' : null],
                    ['Berat', $student->weight ? $student->weight.' kg' : null],
                ] as [$label, $value])
                    <x-ui.detail-item :label="$label">{{ $value ?: '—' }}</x-ui.detail-item>
                @endforeach
            </x-ui.detail-list>
        </x-ui.form-section>

        @if($student->payload)
            <details class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-6">
                <summary class="cursor-pointer font-bold text-[var(--ui-fg-strong)]">
                    Semua field asli Dapodik ({{ count($student->payload) }})
                </summary>
                <x-ui.detail-list class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($student->payload as $key => $value)
                        <x-ui.detail-item :label="str_replace('_', ' ', $key)" class="rounded-lg bg-[var(--ui-surface-soft)] p-3">
                            <span class="break-words text-sm">{{ is_scalar($value) ? ($value ?: '—') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span>
                        </x-ui.detail-item>
                    @endforeach
                </x-ui.detail-list>
            </details>
        @endif
    </div>
</x-layouts.tailwind-app>
