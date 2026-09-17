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
                <td class="min-w-64">{{ $row['account_name'] ?: '—' }}</td>
                <td class="whitespace-nowrap text-right">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                <td class="whitespace-nowrap text-right">Rp {{ number_format($row['min_price'], 0, ',', '.') }} – Rp {{ number_format($row['max_price'], 0, ',', '.') }}</td>
                <td>{{ $row['spending_code'] ?: '—' }}</td>
                <td class="text-center font-semibold">{{ number_format($row['usage_count'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell"><p class="font-semibold text-[var(--ui-fg-strong)]">Cari acuan barang ARKAS.</p><p class="mt-1 text-base text-[var(--ui-fg-muted)]">Masukkan nama atau kata kunci barang untuk menampilkan barang dan rekening yang sesuai.</p></td></tr>
        @endforelse
    </tbody>
</x-ui.table>

<x-ui.server-pagination :paginator="$rows" noun="acuan barang" />
