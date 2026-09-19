<x-layouts.tailwind-app>
    @php
        $spjTypeLabel = fn ($value) => match (strtoupper((string) $value)) {
            'JASA_HONORARIUM', 'HONOR_PEGAWAI' => 'Honor Pegawai',
            'JASA_LAINNYA' => 'Jasa Lainnya',
            'BARANG' => 'Barang',
            'KONSUMSI' => 'Konsumsi',
            'PEMELIHARAAN' => 'Pemeliharaan',
            'SPPD' => 'SPPD',
            default => ucwords(strtolower(str_replace('_', ' ', (string) $value))),
        };

        $participantRows = $transaction->participants
            ->map(fn ($participant) => [
                'name' => $participant->name,
                'position' => $participant->position,
                'nip' => $participant->nip,
                'nuptk' => $participant->nuptk,
                'portions' => (int) $participant->portions,
            ])
            ->values()
            ->all();

        if (strtoupper((string) $transaction->spj_category) === 'KONSUMSI' && $participantRows === []) {
            $participantRows = collect($participantRoster ?? [])
                ->map(fn ($employee) => [
                    'name' => $employee->name,
                    'position' => $employee->position ?: $employee->staff_type,
                    'nip' => $employee->nip,
                    'nuptk' => $employee->nuptk,
                    'portions' => 1,
                ])
                ->values()
                ->all();
        }

        $participantRows = old('participants', $participantRows);
        $purchaseDetails = $transaction->goods->first();
        $transactionDateLimit = $transaction->transaction_date?->format('Y-m-d');
        $orderDate = $purchaseDetails?->order_date?->format('Y-m-d') ?: $transaction->order_date?->format('Y-m-d') ?: $transactionDateLimit;
        $bapDate = $purchaseDetails?->bap_date?->format('Y-m-d') ?: $transaction->bap_date?->format('Y-m-d') ?: $transactionDateLimit;
        $bastDate = $purchaseDetails?->bast_date?->format('Y-m-d') ?: $transaction->bast_date?->format('Y-m-d') ?: $transactionDateLimit;
        $workDetails = $transaction->workOrder;
        $workerRows = $transaction->workers->map(fn ($worker) => [
            'name' => $worker->name,
            'job_description' => $worker->job_description,
            'work_days' => $worker->work_days,
            'daily_rate' => $worker->daily_rate,
            'is_receipt_recipient' => (bool) $worker->is_receipt_recipient,
            'notes' => $worker->notes,
        ])->values()->all();
        $workerRows = old('spj_category', $transaction->spj_category) === 'PEMELIHARAAN'
            ? old('workers', $workerRows)
            : $workerRows;
        $selectedSpjType = strtoupper((string) old('spj_category', $transaction->spj_category));
        $compatibilityOverlayEdit = true;
    @endphp

    <div class="spj-semantic-workspace space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])">← Mode baca Paket</x-ui.button>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status-badge status="READY" label="Effective-context overlay edit" />
                <x-ui.status-badge :status="$package->status" />
            </div>
        </div>

        <section class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-bold">Mode edit transisi hanya untuk operator overlay Paket.</p>
            <p class="mt-1">Fiscal year legacy, fakta ARKAS/BKU, pajak, numbering, FINAL, settlement, dan linkage pemeliharaan tidak dapat diubah dari layar ini.</p>
        </section>

        @include('spj.partials.package.transaction-summary')

        <section class="overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow" x-data="{saving:false}">
            <div class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-4 py-3">
                <h1 class="text-base font-bold text-[var(--ui-fg-strong)]">Isian Manual Paket SPJ</h1>
                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Perubahan disimpan pada overlay legacy authoritative setelah effective-context authorization lulus.</p>
            </div>

            <form
                id="spj-manual-form"
                method="POST"
                action="{{ route('spj.update', $package->id) }}"
                data-source-siplah="{{ $transaction->is_siplah ? '1' : '0' }}"
                data-compatibility-editor="1"
                class="space-y-4 p-4"
                @submit="if (!$event.defaultPrevented) saving=true"
            >
                @csrf
                @method('PUT')

                <div x-show="saving" class="flex items-center justify-center py-4"><x-loading-spinner /></div>

                <fieldset x-show="!saving" class="space-y-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                        <div class="grid gap-3">
                            <div>
                                <label class="text-xs font-bold text-amber-900">Kategori SPJ <span class="text-rose-600">*</span></label>
                                <x-ui.select id="spj-type" name="spj_category" class="mt-1">
                                    <option value="">Pilih kategori</option>
                                    @foreach(['BARANG','KONSUMSI','PEMELIHARAAN','JASA_LAINNYA','SPPD','HONOR_PEGAWAI'] as $value)
                                        <option value="{{ $value }}" @selected(
                                            in_array($selectedSpjType, ['JASA_HONORARIUM', 'HONOR_PEGAWAI'], true)
                                                && in_array(strtoupper((string) $value), ['JASA_HONORARIUM', 'HONOR_PEGAWAI'], true)
                                            || old('spj_category', $transaction->spj_category) === $value
                                        )>{{ $spjTypeLabel($value) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            <p class="text-xs text-amber-800">Perubahan kategori pada Paket READY mengembalikan status ke DRAFT agar validasi diulang.</p>
                        </div>
                    </div>

                    @include('spj.partials.package.common')
                    @include('spj.partials.package.categories.honor-pegawai')
                    @include('spj.partials.package.categories.sppd')
                    @include('spj.partials.package.categories.barang')
                    @include('spj.partials.package.categories.konsumsi')
                    @include('spj.partials.package.categories.pemeliharaan')
                    @include('spj.partials.package.categories.jasa-lainnya')

                    <div class="flex flex-wrap justify-end gap-2 border-t border-[var(--ui-line)] pt-3">
                        <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $package->id])">Batal</x-ui.button>
                        <x-ui.button type="submit" x-bind:disabled="saving">
                            <span x-text="saving ? 'Menyimpan...' : 'Simpan Isian Paket'"></span>
                        </x-ui.button>
                    </div>
                </fieldset>
            </form>
        </section>
    </div>
</x-layouts.tailwind-app>
