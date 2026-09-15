<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header title="Pilih Transaksi Pembayaran Honor"
            subtitle="Pilih sendiri transaksi kategori Honor Pegawai yang akan digabung dalam satu laporan penerimaan pembayaran honor."
            kicker="Laporan SPJ · Honor Pegawai">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'laporan'])">Kembali ke Laporan SPJ</x-ui.button></x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('spj.honor-payments.compose') }}" class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4">
                <div><h2 class="font-bold text-[var(--ui-fg-strong)]">Transaksi Honor Pegawai</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Hanya transaksi dengan kategori <span class="font-bold">Honor Pegawai</span> dan rincian penerima honor yang ditampilkan.</p></div>
                <x-ui.button type="submit">Lanjutkan Susun Laporan</x-ui.button>
            </div>
            @if ($errors->any())<div class="mx-5 mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-[var(--ui-line)] text-sm"><thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-3 py-3 text-center"><span class="sr-only">Pilih</span></th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">BPU / Tanggal</th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Uraian / Penerima</th><th class="px-3 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Periode</th><th class="px-3 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Bruto</th><th class="px-3 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Diterima</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">
                @forelse ($transactions as $transaction)
                    <tr class="hover:bg-[var(--ui-surface-soft)]"><td class="px-3 py-4 text-center"><input type="checkbox" name="transaction_ids[]" value="{{ $transaction->id }}" class="h-4 w-4 rounded border-[var(--ui-line-strong)]" @checked(in_array($transaction->id, old('transaction_ids', [])))></td><td class="px-3 py-4"><div class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $transaction->no_bukti }}</div><div class="text-xs text-[var(--ui-fg-muted)]">{{ $transaction->transaction_date?->translatedFormat('d F Y') }}</div></td><td class="px-3 py-4"><div class="font-semibold text-[var(--ui-fg)]">{{ $transaction->payment_description ?: $transaction->description }}</div><div class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->honors->pluck('name')->filter()->unique()->implode(', ') ?: $transaction->recipient_name }}</div></td><td class="px-3 py-4 text-[var(--ui-fg)]">{{ $transaction->transaction_date?->translatedFormat('F Y') ?: '-' }}</td><td class="px-3 py-4 text-right">{{ number_format((float) $transaction->gross_amount, 0, ',', '.') }}</td><td class="px-3 py-4 text-right font-bold">{{ number_format((float) $transaction->net_amount, 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-[var(--ui-fg-muted)]">Belum ada transaksi Honor Pegawai yang memiliki rincian penerima honor.</td></tr>
                @endforelse
            </tbody></table></div>
        </form>
    </div>
</x-layouts.tailwind-app>
