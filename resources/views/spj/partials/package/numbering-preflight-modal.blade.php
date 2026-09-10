@php
    $preflightOrderDate = $purchaseDetails?->order_date ?: $transaction->order_date;
    $preflightBapDate = $purchaseDetails?->bap_date ?: $transaction->bap_date;
    $preflightBastDate = $purchaseDetails?->bast_date ?: $transaction->bast_date;
    $preflightIssues = $validationIssues ?? [];
    $preflightCategory = strtoupper((string) ($transaction->spj_category ?? ''));
    $preflightDetailSummary = match ($preflightCategory) {
        'KONSUMSI' => $transaction->participants->count().' peserta' . ($transaction->event_name ? ' · '.$transaction->event_name : ''),
        'HONOR_PEGAWAI' => $transaction->honors->count().' penerima honor',
        'SPPD' => $transaction->travels->count().' rincian perjalanan',
        'PEMELIHARAAN' => $transaction->workers->count().' pekerja',
        'JASA_LAINNYA' => $transaction->serviceRecipients->count().' penerima jasa',
        default => $transaction->items->count().' rincian barang',
    };
@endphp

<div x-data="{ open: false }" class="mt-4">
    <button type="button" @click="open = true" class="rounded-md bg-violet-600 px-4 py-2 text-base font-bold text-white shadow hover:bg-violet-700">
        Periksa &amp; Terbitkan nomor SPJ
    </button>
    <p class="mt-2 text-xs text-slate-500">Sistem menampilkan status validasi, data sumber, dan isian operator terakhir sebelum nomor diterbitkan.</p>

    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="open = false">
        <div x-show="open" x-transition @click.outside="open = false" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--ui-line)] px-5 py-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">Verifikasi sebelum penomoran</p>
                    <h3 class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Pastikan data dokumen sudah benar</h3>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Setelah dikonfirmasi, nomor mengikuti format aktif dan tanggal peristiwa dokumen.</p>
                </div>
                <button type="button" @click="open = false" class="rounded-md px-2 py-1 text-xl text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]" aria-label="Tutup">×</button>
            </div>

            @if(empty($preflightIssues))
                <div class="mx-5 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">
                    Semua pemeriksaan validasi lolos. Paket siap dinomori.
                </div>
            @else
                <div class="mx-5 mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                    <p class="font-bold">{{ count($preflightIssues) }} masalah harus diperbaiki dahulu (server tetap menolak penomoran):</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach($preflightIssues as $issue)
                            <li><strong>{{ $issue['label'] ?? 'Periksa' }}:</strong> {{ $issue['message'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="px-5 pt-4 text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Data sumber (ARKAS/BKU, hanya baca)</p>
            <div class="grid gap-3 p-5 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    'TGL_RKAS' => $transaction->rkas_date?->format('d-m-Y'),
                    'TGL_TRANSAKSI' => $transaction->transaction_date?->format('d-m-Y'),
                    'NO_BUKTI' => $transaction->no_bukti,
                    'BRUTO' => $rupiah($transaction->gross_amount),
                    'TGL_PESANAN' => $preflightOrderDate?->format('d-m-Y'),
                    'TGL_BAP' => $preflightBapDate?->format('d-m-Y'),
                    'TGL_BAST' => $preflightBastDate?->format('d-m-Y'),
                ] as $label => $value)
                    <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $label }}</p>
                        <p class="mt-1 break-words text-sm font-bold text-[var(--ui-fg-strong)]">{{ filled($value) ? $value : '—' }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">PAYMENT_DESCRIPTION</p>
                    <p class="mt-1 whitespace-pre-line text-sm font-semibold text-[var(--ui-fg-strong)]">{{ filled($transaction->payment_description) ? $transaction->payment_description : '— BELUM DIISI —' }}</p>
                </div>
            </div>

            <p class="px-5 text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Isian operator (Data Umum SPJ)</p>
            <div class="grid gap-3 p-5 pt-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    'KATEGORI' => $preflightCategory ?: '— BELUM DIPILIH —',
                    'PENERIMA' => $transaction->effective_receipt_recipient_name ?: '— BELUM DIISI —',
                    'CARA_BAYAR' => $transaction->payment_method ?: '— BELUM DIPILIH —',
                    'PENYEDIA' => $transaction->vendor_name ?: ($transaction->recipient_name ?: '— BELUM DIISI —'),
                ] as $label => $value)
                    <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $label }}</p>
                        <p class="mt-1 break-words text-sm font-bold text-[var(--ui-fg-strong)]">{{ $value }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">RINCIAN_{{ $preflightCategory ?: 'KATEGORI' }}</p>
                    <p class="mt-1 text-sm font-semibold text-[var(--ui-fg-strong)]">{{ $preflightDetailSummary }}</p>
                </div>
            </div>

            <div class="border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4">
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Penomoran tetap divalidasi oleh server. Data Umum wajib lengkap dan Bulan Pesanan tidak boleh lebih awal dari Bulan RKAS.
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-md border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-4 py-2 text-sm font-bold text-[var(--ui-fg)] hover:bg-[var(--ui-surface-soft)]">Kembali periksa</button>
                    <form method="POST" action="{{ route('spj.assign-number', $package->id) }}">
                        @csrf
                        <button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-violet-700">Konfirmasi &amp; Terbitkan Nomor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
