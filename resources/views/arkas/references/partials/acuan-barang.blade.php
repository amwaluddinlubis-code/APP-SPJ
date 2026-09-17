<x-ui.table min-width="1120px" pagination="server">
    <thead>
        <tr>
            <th class="w-16 text-center">No</th>
            <th class="min-w-[28rem]">Nama Barang / Jasa</th>
            <th>Satuan</th>
            <th>Kode Rekening</th>
            <th>Rekening</th>
            <th class="text-right">Harga Maksimal</th>
            <th>Kode Belanja</th>
            <th class="text-center">Dipakai RKAS</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td class="text-center text-xs text-[var(--ui-fg-muted)]">{{ $rows->firstItem() + $index }}</td>
                <td class="min-w-[28rem] max-w-2xl text-[var(--ui-fg)]">{{ $row['name'] ?: '—' }}</td>
                <td>{{ $row['unit'] ?: '—' }}</td>
                <td class="font-mono text-xs text-[var(--theme-content-accent)]">{{ $row['account_code'] ?: '—' }}</td>
                <td class="min-w-64">{{ $row['account_name'] ?: '—' }}</td>
                <td class="whitespace-nowrap text-right">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                <td>{{ $row['spending_code'] ?: '—' }}</td>
                <td class="text-center font-semibold">{{ number_format($row['usage_count'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-cell"><p class="font-semibold text-[var(--ui-fg-strong)]">Cari acuan barang ARKAS.</p><p class="mt-1 text-base text-[var(--ui-fg-muted)]">Masukkan nama atau kata kunci barang untuk menampilkan barang dan rekening yang sesuai.</p></td></tr>
        @endforelse
    </tbody>
</x-ui.table>

<x-ui.server-pagination :paginator="$rows" noun="acuan barang" />
