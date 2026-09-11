<x-layouts.tailwind-app>
    @php
        $transaction = $package->transaction;
        $packageUrl = route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]);
        $transactionUrl = route('transactions.show', $transaction->id);
        $failedChecks = $checklist->where('passed', false)->values();
        $blockingRequirements = $documentRequirements->filter(fn ($item) => $item['applicable'] && $item['required'] && ! $item['available'])->values();
        $blockingCount = $failedChecks->count() + $blockingRequirements->count();
        $passedChecks = $checklist->where('passed', true)->values();
        $readyRequirements = $documentRequirements->filter(fn ($item) => $item['applicable'] && $item['available'])->values();
        $optionalMissing = $documentRequirements->filter(fn ($item) => $item['applicable'] && ! $item['required'] && ! $item['available'])->values();
        $notApplicable = $documentRequirements->filter(fn ($item) => ! $item['applicable'])->values();
        $doneCount = $passedChecks->count() + $readyRequirements->count();
        $isTransactionUrl = fn ($url) => str_starts_with((string) $url, $transactionUrl);
    @endphp
    <div class="spj-semantic-workspace space-y-6">
        <x-page-header
            :title="'Checklist — '.($transaction->no_bukti ?: 'Tanpa nomor bukti')"
            :subtitle="($transaction->payment_description ?: $transaction->description ?: 'Uraian belum tersedia').' · Rp '.number_format((float) $transaction->gross_amount, 0, ',', '.')"
            kicker="Checklist Paket SPJ"
        >
            <x-slot:actions>
                @if($canMarkReady)
                    <form method="POST" action="{{ route('spj.ready', $package->id) }}">@csrf<x-ui.button type="submit">Tandai siap diproses →</x-ui.button></form>
                @endif
                <x-ui.button variant="secondary" :href="$packageUrl">Buka paket</x-ui.button>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Jalur pengadaan" :value="$requirementSummary['channel']" :hint="str_replace('_', ' ', (string) $transaction->spj_category) ?: 'Tanpa kategori'" />
                <x-stat-item label="Dokumen wajib siap" :value="$requirementSummary['required_ready'].' / '.$requirementSummary['required_total']" :hint="$progress.'% lengkap'" />
                <x-stat-item label="Masih menghalangi" :value="$blockingCount" :hint="$completedChecks.'/'.$totalChecks.' pemeriksaan lolos'" :value-class="$blockingCount > 0 ? 'text-amber-700' : 'text-emerald-700'" />
                <x-stat-item label="Status paket" :value="$package->status" :hint="$transaction->transaction_date?->translatedFormat('d F Y') ?: 'Tanggal belum tersedia'" />
            </div>
        </x-page-header>

        @if($blockingCount > 0)
            <section class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
                <p class="font-bold">Belum siap diberi nomor — {{ $blockingCount }} hal perlu dilengkapi.</p>
                <p class="mt-0.5">Kerjakan berurutan dari nomor 1. Setiap baris menunjukkan di mana memperbaikinya (Paket atau Transaksi).</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <div class="border-b border-[var(--ui-line)] px-5 py-3">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Yang menghalangi ({{ $blockingCount }})</h2>
                </div>
                <ol class="divide-y divide-[var(--ui-line)]">
                    @foreach($failedChecks as $index => $check)
                        <li class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-black text-amber-800">{{ $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[var(--ui-fg-strong)]">{{ $check['label'] }} <x-ui.badge variant="neutral">{{ $isTransactionUrl($check['url']) ? 'Transaksi' : 'Paket' }}</x-ui.badge></p>
                                    <p class="mt-0.5 text-sm leading-6 text-amber-800">{{ $check['message'] }}</p>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" :href="$check['url']" class="shrink-0 text-xs">Perbaiki →</x-ui.button>
                        </li>
                    @endforeach
                    @foreach($blockingRequirements as $index => $item)
                        @php($fixUrl = $item['key'] === 'transaction_details' ? $transactionUrl.'#rincian-transaksi' : $packageUrl.'#spj-manual-form')
                        <li class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-black text-amber-800">{{ $failedChecks->count() + $index + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="font-bold text-[var(--ui-fg-strong)]">{{ $item['label'] }} <x-ui.badge variant="neutral">{{ $item['key'] === 'transaction_details' ? 'Transaksi' : 'Paket' }}</x-ui.badge></p>
                                    <p class="mt-0.5 text-sm leading-6 text-amber-800">{{ $item['message'] }}</p>
                                    <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $item['group'] }} · {{ $item['source'] }}</p>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" :href="$fixUrl" class="shrink-0 text-xs">Perbaiki →</x-ui.button>
                        </li>
                    @endforeach
                </ol>
            </section>
        @else
            <section class="rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-900">
                <p class="font-bold">Semua kebutuhan wajib lengkap — paket siap dilanjutkan.</p>
                @if($canMarkReady)
                    <p class="mt-0.5">Gunakan tombol “Tandai siap diproses” di atas untuk melanjutkan ke penomoran.</p>
                @elseif($package->status !== 'DRAFT')
                    <p class="mt-2"><x-ui.status-badge :status="$package->status" /></p>
                @elseif(!$canEdit)
                    <p class="mt-0.5">Mode pemeriksa: data dapat dilihat, tetapi status paket tidak dapat diubah.</p>
                @endif
            </section>
        @endif

        <details class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-[var(--ui-fg-strong)]">Sudah lengkap ({{ $doneCount }}) — klik untuk melihat</summary>
            <ul class="divide-y divide-[var(--ui-line)] border-t border-[var(--ui-line)]">
                @foreach($passedChecks as $check)
                    <li class="flex items-center gap-2.5 px-5 py-2 text-sm"><span class="font-black text-emerald-600">✓</span><span class="font-semibold text-[var(--ui-fg-strong)]">{{ $check['label'] }}</span><span class="text-xs text-[var(--ui-fg-muted)]">{{ $check['group'] }}</span></li>
                @endforeach
                @foreach($readyRequirements as $item)
                    <li class="flex items-center gap-2.5 px-5 py-2 text-sm"><span class="font-black text-emerald-600">✓</span><span class="font-semibold text-[var(--ui-fg-strong)]">{{ $item['label'] }}</span><span class="text-xs text-[var(--ui-fg-muted)]">{{ $item['group'] }}</span></li>
                @endforeach
            </ul>
        </details>

        @if($optionalMissing->isNotEmpty() || $notApplicable->isNotEmpty())
            <details class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-[var(--ui-fg-muted)]">Opsional / tidak berlaku ({{ $optionalMissing->count() + $notApplicable->count() }}) — tidak memblokir</summary>
                <ul class="divide-y divide-[var(--ui-line)] border-t border-[var(--ui-line)]">
                    @foreach($optionalMissing as $item)
                        <li class="px-5 py-2 text-sm"><span class="font-semibold text-[var(--ui-fg-muted)]">{{ $item['label'] }}</span> <x-ui.badge variant="neutral">Opsional</x-ui.badge><p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $item['message'] }}</p></li>
                    @endforeach
                    @foreach($notApplicable as $item)
                        <li class="px-5 py-2 text-sm"><span class="font-semibold text-[var(--ui-fg-muted)]">{{ $item['label'] }}</span> <x-ui.badge variant="neutral">Tidak berlaku</x-ui.badge></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</x-layouts.tailwind-app>
