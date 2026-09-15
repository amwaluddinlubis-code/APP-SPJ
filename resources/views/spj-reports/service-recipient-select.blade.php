<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header title="Pilih Transaksi Pembayaran Jasa Lainnya"
            subtitle="Pilih transaksi kategori Jasa Lainnya yang akan disusun menjadi daftar penerima pembayaran."
            kicker="LAPORAN SPJ · JASA LAINNYA">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'laporan'])">Kembali ke Laporan SPJ</x-ui.button></x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('spj.service-recipients.compose') }}" class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4">
                <div>
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Transaksi Jasa Lainnya</h2>
                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Hanya transaksi dengan kategori <span class="font-mono font-bold">JASA_LAINNYA</span> dan rincian penerima jasa yang ditampilkan.</p>
                </div>
                <x-ui.button type="submit">Lanjutkan Susun Laporan</x-ui.button>
            </div>
            <div class="overflow-x-auto p-5">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-3 py-3 text-center"><span class="sr-only">Pilih</span></th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">BPU / Tanggal</th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Uraian</th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Penerima Jasa</th><th class="px-3 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Bruto</th><th class="px-3 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Dibayarkan</th></tr></thead>
                    <tbody class="divide-y divide-[var(--ui-line)]">
                        @forelse ($transactions as $transaction)
                            <tr class="hover:bg-[var(--ui-surface-soft)]"><td class="px-3 py-4 text-center"><input type="checkbox" name="transaction_ids[]" value="{{ $transaction->id }}" class="h-4 w-4 rounded border-[var(--ui-line-strong)]" @checked(in_array($transaction->id, old('transaction_ids', [])))></td><td class="px-3 py-4"><div class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $transaction->no_bukti }}</div><div class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</div></td><td class="px-3 py-4 font-semibold text-[var(--ui-fg)]">{{ $transaction->payment_description ?: $transaction->description }}</td><td class="px-3 py-4 text-[var(--ui-fg-muted)]">{{ $transaction->serviceRecipients->pluck('name')->filter()->unique()->implode(', ') }}</td><td class="px-3 py-4 text-right">{{ number_format((float) $transaction->gross_amount, 0, ',', '.') }}</td><td class="px-3 py-4 text-right font-bold">{{ number_format((float) $transaction->net_amount, 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center text-[var(--ui-fg-muted)]">Belum ada transaksi Jasa Lainnya yang memiliki rincian penerima jasa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</x-layouts.tailwind-app>
