<x-ui.table min-width="1280px" pagination="server">
    <thead>
        <tr>
            <th class="w-16 text-center">No</th>
            <th>ID Acuan</th>
            <th>Nama Barang / Jasa</th>
            <th>Satuan</th>
            <th>Kode Rekening</th>
            <th>Rekening</th>
            <th class="text-right">Harga Referensi</th>
            <th class="text-right">Rentang Harga</th>
            <th>Kode Belanja</th>
            <th class="text-center">Dipakai RKAS</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td class="text-center text-xs text-[var(--ui-fg-muted)]">{{ $rows->firstItem() + $index }}</td>
                <td class="font-mono text-xs text-[var(--theme-content-accent)]">{{ $row['code'] }}</td>
                <td class="max-w-sm text-[var(--ui-fg)]">{{ $row['name'] ?: '—' }}</td>
                <td>{{ $row['unit'] ?: '—' }}</td>
                <td class="font-mono text-xs text-[var(--theme-content-accent)]">{{ $row['account_code'] ?: '—' }}</td>
                <td class="min-w-64">
                    <div>{{ $row['account_name'] ?: '—' }}</div>
                    @if (count($row['account_candidates'] ?? []) > 0 && str_ends_with($row['account_code'], '*'))
                        <details class="mt-1 text-xs">
                            <summary class="cursor-pointer text-[var(--theme-content-accent)]">Lihat kandidat rekening</summary>
                            <div class="mt-1 max-h-36 overflow-y-auto rounded border border-[var(--ui-line)] bg-[var(--ui-surface-muted)] p-2 text-[var(--ui-fg-muted)]">
                                @foreach ($row['account_candidates'] as $candidate)
                                    <div>{{ $candidate }}</div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </td>
                <td class="whitespace-nowrap text-right">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                <td class="whitespace-nowrap text-right">Rp {{ number_format($row['min_price'], 0, ',', '.') }} – Rp {{ number_format($row['max_price'], 0, ',', '.') }}</td>
                <td>{{ $row['spending_code'] ?: '—' }}</td>
                <td class="text-center font-semibold">{{ number_format($row['usage_count'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell"><p class="font-semibold text-[var(--ui-fg-strong)]">Belum ada acuan barang.</p><p class="mt-1 text-base text-[var(--ui-fg-muted)]">Jalankan sinkronisasi raw ARKAS untuk mengisi referensi tahun aktif.</p></td></tr>
        @endforelse
    </tbody>
</x-ui.table>

<x-ui.server-pagination :paginator="$rows" noun="acuan barang" />
