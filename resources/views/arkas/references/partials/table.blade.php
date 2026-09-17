<x-ui.table min-width="720px" pagination="server">
    <thead>
        <tr>
            <th class="w-16 text-center">No</th>
            <th>Kode</th>
            <th>Nama / Uraian</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td class="text-center text-xs text-[var(--ui-fg-muted)]">{{ $rows->firstItem() + $index }}</td>
                <td class="font-mono text-sm font-semibold text-[var(--theme-content-accent)]">{{ $row['code'] }}</td>
                <td class="text-[var(--ui-fg)]">{{ $row['name'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="empty-cell">
                    <p class="font-semibold text-[var(--ui-fg-strong)]">Belum ada referensi.</p>
                    <p class="mt-1 text-base text-[var(--ui-fg-muted)]">Jalankan sinkronisasi raw ARKAS untuk mengisi referensi.</p>
                </td>
            </tr>
        @endforelse
    </tbody>
</x-ui.table>

<x-ui.server-pagination :paginator="$rows" noun="referensi" />
