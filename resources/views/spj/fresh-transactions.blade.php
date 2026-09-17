<x-layouts.tailwind-app>
    @php
        $payloadValue = static fn (?array $payload, array $keys): ?string => collect($keys)
            ->map(fn (string $key): mixed => $payload[$key] ?? null)
            ->first(fn (mixed $value): bool => filled($value))
            ? (string) collect($keys)->map(fn (string $key): mixed => $payload[$key] ?? null)->first(fn (mixed $value): bool => filled($value))
            : null;
    @endphp

    <div class="space-y-6">
        <x-page-header
            title="Transaksi Fresh SPJ"
            subtitle="Daftar indeks transaksi baru yang membaca fakta ARKAS langsung dari raw mirror."
            kicker="WORKSPACE SPJ FRESH"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="database" :href="route('arkas.importer')">Sinkronisasi ARKAS</x-ui.button>
                <x-ui.button variant="secondary" :href="route('spj.index')">Ruang Kerja SPJ</x-ui.button>
            </x-slot:actions>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Transaksi fresh" :value="number_format($stats['total'], 0, ',', '.')" hint="Konteks aktif" />
                <x-stat-item label="Source aktif" :value="number_format($stats['active'], 0, ',', '.')" hint="Dibaca dari ARKAS" />
                <x-stat-item label="Perlu rekonsiliasi" :value="number_format($stats['reconciliation'], 0, ',', '.')" hint="Perubahan source" />
                <x-stat-item label="Sudah dipaketkan" :value="number_format($stats['packaged'], 0, ',', '.')" hint="Workspace fresh" />
            </div>
        </x-page-header>

        <x-ui.form-section title="Filter Transaksi Fresh" description="Konteks: tahun {{ $activeYear?->year ?: '—' }} · {{ $activeYear?->fundSource?->name ?: ($activeYear?->fund_source ?: '—') }}. Fakta ARKAS bersifat readonly.">
            <form method="GET" class="grid gap-4 md:grid-cols-[1fr_14rem_10rem_auto] md:items-end">
                <x-ui.field label="Cari source key" for="fresh-search">
                    <x-ui.input id="fresh-search" name="q" value="{{ $search }}" placeholder="ID sumber ARKAS..." />
                </x-ui.field>
                <x-ui.field label="Status source" for="fresh-status">
                    <x-ui.select id="fresh-status" name="status">
                        <option value="">Semua status</option>
                        <option value="ACTIVE" @selected($status === 'ACTIVE')>ACTIVE</option>
                        <option value="DELETED" @selected($status === 'DELETED')>DELETED</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="Per halaman" for="fresh-per-page">
                    <x-ui.select id="fresh-per-page" name="per_page">
                        @foreach([25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected($transactions->perPage() === $option)>{{ $option }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="submit" icon="filter">Terapkan</x-ui.button>
            </form>
        </x-ui.form-section>

        <x-ui.form-section title="Fakta ARKAS" description="Kolom uraian, tanggal, bukti, dan rekening dibaca dari payload raw mirror; tidak disalin menjadi sumber kedua.">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-[var(--ui-line)] text-left text-xs uppercase tracking-wide" style="color: var(--ui-fg-muted)">
                        <tr>
                            <th class="px-4 py-3">Tanggal / Bukti</th>
                            <th class="px-4 py-3">Uraian ARKAS</th>
                            <th class="px-4 py-3">Rekening</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">SPJ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)]">
                        @forelse($transactions as $transaction)
                            @php($payload = $transaction->rawMirrorRow?->payload ?? [])
                            <tr class="align-top hover:bg-[var(--ui-surface-soft)]">
                                <td class="px-4 py-4">
                                    <div class="font-semibold" style="color: var(--ui-fg-strong)">{{ $payloadValue($payload, ['no_bukti', 'nomor_bukti']) ?: 'Tanpa nomor bukti' }}</div>
                                    <div class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $payloadValue($payload, ['tanggal_transaksi', 'tanggal']) ?: 'Tanpa tanggal' }}</div>
                                </td>
                                <td class="max-w-md px-4 py-4">
                                    <div style="color: var(--ui-fg-strong)">{{ $payloadValue($payload, ['uraian', 'description']) ?: 'Uraian belum tersedia' }}</div>
                                    @if($transaction->payment_description)<div class="mt-1 text-xs" style="color: var(--ui-content-accent)">Overlay operator tersedia</div>@endif
                                </td>
                                <td class="px-4 py-4 font-mono text-xs" style="color: var(--ui-fg-muted)">{{ $payloadValue($payload, ['kode_rekening', 'account_code']) ?: '—' }}</td>
                                <td class="px-4 py-4">
                                    <x-ui.badge>{{ $transaction->source_status }}</x-ui.badge>
                                    <div class="mt-1 max-w-40 truncate font-mono text-xs" title="{{ $transaction->source_key }}" style="color: var(--ui-fg-muted)">{{ $transaction->source_key }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    @if($transaction->package)
                                        <x-ui.badge>{{ $transaction->package->status }}</x-ui.badge>
                                    @else
                                        <span class="text-xs" style="color: var(--ui-fg-muted)">Belum dipaketkan</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center" style="color: var(--ui-fg-muted)">Belum ada transaksi fresh pada konteks aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="mt-5"><x-ui.server-pagination :paginator="$transactions" noun="transaksi fresh" /></div>
            @endif
        </x-ui.form-section>
    </div>
</x-layouts.tailwind-app>
