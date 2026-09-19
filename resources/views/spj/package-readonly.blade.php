<x-layouts.tailwind-app>
    @php
        $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
            'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
            default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
        };
        $packageCategory = strtoupper((string) $transaction->spj_category);
        $transactionDetailIdentifier = $transaction->source_key ?: $transaction->id;
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket'])">← Daftar Paket</x-ui.button>
            <div class="flex flex-wrap items-center gap-2">
                @if($package->isEditable())
                    <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $package->id, 'edit' => 1])">Edit Isian Manual</x-ui.button>
                @endif
                @if($package->status === 'DRAFT')
                    <x-ui.button variant="secondary" :href="route('spj.checklist', $package->id)">Buka Checklist</x-ui.button>
                @endif
                <x-ui.status-badge status="READY" label="Mode baca kompatibilitas" />
            </div>
        </div>

        <section class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            <p class="font-bold">Paket dibuka dalam mode baca effective-context.</p>
            <p class="mt-1">Data sumber legacy tidak diubah. DRAFT/READY dapat membuka editor overlay khusus setelah effective-context authorization. Penomoran effective hanya tersedia untuk Paket READY setelah preflight server lulus; lifecycle lanjutan tetap tidak tersedia.</p>
        </section>

        @if($package->status === 'READY' && ($effectiveNumberingPreflight['active'] ?? false))
            @php
                $activeSpjDocument = $package->documents->first(fn ($document) => $document->document_type === 'SPJ' && $document->scope_key === 'MAIN' && in_array($document->status, ['NUMBERED', 'FINAL'], true) && filled($document->document_number));
                $hasActiveSpjNumber = $activeSpjDocument !== null;
                $cancelledSpjDocument = $package->documents->where('document_type', 'SPJ')->where('scope_key', 'MAIN')->where('status', 'CANCELLED')->sortByDesc('id')->first();
                $packageCategory = strtoupper((string) $transaction->spj_category);
                $isHonorPackage = $packageCategory === 'HONOR_PEGAWAI';
                $isGoodsPackage = $packageCategory === 'BARANG';
            @endphp
            <section class="mx-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <div class="border-b border-[var(--ui-line)] px-4 py-3.5">
                    <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Penomoran effective-context</h2>
                    <p class="mt-0.5 text-sm text-[var(--ui-fg-muted)]">Action ini memakai service issuance V2. Batch, finalisasi, dan perubahan lifecycle lain tetap tertutup.</p>
                </div>
                <div class="p-4">
                    @include('spj.partials.package.numbering-preflight-modal')
                </div>
            </section>
        @endif

        @include('spj.partials.package.transaction-summary')

        <section class="mx-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            @include('spj.partials.package.items-readonly')
        </section>

        <section class="mx-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-4 py-3.5">
                <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Validasi Sebelum Cetak</h2>
                <p class="mt-0.5 text-sm {{ $validationIssues ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ $validationIssues ? count($validationIssues).' data wajib masih perlu dilengkapi.' : 'Semua data wajib untuk cetak sudah lengkap.' }}
                </p>
            </div>
            @if($validationIssues)
                <div class="divide-y divide-amber-100 bg-amber-50/40">
                    @foreach($validationIssues as $issue)
                        <div class="px-4 py-2.5 text-sm">
                            <span class="font-bold text-amber-800">{{ $issue['label'] }}</span>
                            <span class="ml-1.5 text-amber-700">{{ $issue['message'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-4 py-3 text-sm text-emerald-700">Paket siap untuk operasi Preview/Download read-only.</div>
            @endif
        </section>

        <section class="mx-5 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ui-line)] px-4 py-3.5">
                <div>
                    <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Dokumen &amp; Template</h2>
                    <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Hanya operasi Preview/Download read-only yang tersedia pada mode kompatibilitas.</p>
                </div>
                @unless($validationIssues || $package->status === 'CANCELLED')
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button variant="secondary" :href="route('spj.preview-package', $package->id)">Preview Paket</x-ui.button>
                        <form method="POST" action="{{ route('spj.download-package-excel', $package->id) }}">@csrf<x-ui.button type="submit" variant="secondary">Download Excel</x-ui.button></form>
                        <form method="POST" action="{{ route('spj.download', $package->id) }}" target="_blank">@csrf<x-ui.button type="submit">Download PDF</x-ui.button></form>
                    </div>
                @endunless
            </div>

            @if($templates->isNotEmpty())
                <div class="divide-y divide-[var(--ui-line)]">
                    @foreach($templates as $template)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $template->name }}</p>
                                <p class="mt-0.5 font-mono text-[11px] text-[var(--theme-content-accent)]">{{ $template->document_type }} · {{ strtoupper($template->format) }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button variant="secondary" :href="route('spj.preview-template', [$package->id, $template->id])">Preview</x-ui.button>
                                @unless($validationIssues || $package->status === 'CANCELLED')
                                    <form method="POST" action="{{ route('spj.download-template', [$package->id, $template->id]) }}">@csrf<x-ui.button type="submit" variant="secondary">Download</x-ui.button></form>
                                    <form method="POST" action="{{ route('spj.download-template-pdf', [$package->id, $template->id]) }}" target="_blank">@csrf<x-ui.button type="submit">PDF</x-ui.button></form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-4 py-6 text-center text-sm text-[var(--ui-fg-muted)]">Belum ada template aktif yang sesuai dengan kategori {{ $spjTypeLabel($packageCategory) }}.</div>
            @endif
        </section>
    </div>
</x-layouts.tailwind-app>
