<x-layouts.tailwind-app>
    <div class="spj-semantic-workspace space-y-6">
        <x-page-header
            kicker="KOREKSI PENOMORAN"
            title="Rollback Penomoran SPJ"
            description="Gunakan hanya untuk memperbaiki urutan penomoran agar kembali mengikuti urutan pembukuan ARKAS. Cancel individual tetap memakai lifecycle dokumen biasa dan tidak mengembalikan sequence."
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('spj.numbering-workflow')">Kembali ke Penomoran</x-ui.button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-5 xl:grid-cols-2">
            <x-section-card title="Rollback dari nomor tertentu" description="Nomor target sampai nomor terakhir akan dilepas. Paket terdampak kembali ke DRAFT dan nomor dapat digunakan ulang sesuai urutan ARKAS.">
                <form method="POST" action="{{ route('spj.numbering.rollback') }}" class="space-y-4">
                    @csrf
                    <x-ui.field label="Mulai dari nomor urut">
                        <x-ui.input type="number" name="sequence_number" min="1" required placeholder="Contoh: 8" />
                    </x-ui.field>
                    <x-ui.field label="Alasan rollback">
                        <textarea name="reason" required maxlength="2000" rows="4" class="w-full rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm text-[var(--ui-fg-strong)]" placeholder="Contoh: Urutan transaksi berbeda dengan pembukuan ARKAS."></textarea>
                    </x-ui.field>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
                        Rollback harus dimulai dari nomor yang benar-benar pernah diterbitkan. Seluruh numbering dari nomor tersebut sampai tail sequence pada konteks aktif akan dihapus dari numbering domain.
                    </div>
                    <x-ui.button type="submit" variant="danger">Rollback dari Nomor Ini</x-ui.button>
                </form>
            </x-section-card>

            <x-section-card title="Batalkan penomoran triwulan" description="Pembatalan berjalan mundur: TW4 → TW3 → TW2 → TW1. Triwulan lebih lama tidak dapat dibatalkan jika triwulan setelahnya masih bernomor.">
                <form method="POST" action="{{ route('spj.quarter-numbering.cancel') }}" class="space-y-4">
                    @csrf
                    <x-ui.field label="Triwulan">
                        <select name="quarter" class="w-full rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm" required>
                            @foreach(range(1, 4) as $candidate)
                                <option value="{{ $candidate }}" @selected($candidate === $quarter)>
                                    Triwulan {{ $candidate }} — {{ $quarterCounts[$candidate] ?? 0 }} paket bernomor
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Alasan pembatalan">
                        <textarea name="reason" required maxlength="2000" rows="4" class="w-full rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm text-[var(--ui-fg-strong)]" placeholder="Contoh: Penomoran harus diulang setelah koreksi data ARKAS."></textarea>
                    </x-ui.field>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs leading-5 text-rose-900">
                        Semua numbering Paket dalam triwulan target akan dilepas, Paket kembali DRAFT, run numbering triwulan dihapus, dan sequence kembali ke checkpoint numbering yang masih sah.
                    </div>
                    <x-ui.button type="submit" variant="danger">Batalkan Penomoran Triwulan</x-ui.button>
                </form>
            </x-section-card>
        </div>

        <x-section-card title="Nomor SPJ saat ini" description="Daftar ini membantu menentukan titik rollback. Cancel individual tidak dilakukan dari halaman ini.">
            <div class="overflow-x-auto rounded-xl border border-[var(--ui-line)]">
                <table data-pagination="none" class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Urut</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Nomor SPJ</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Bukti ARKAS</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($documents as $document)
                            <tr>
                                <td class="px-4 py-3 font-mono font-bold">{{ $document->sequence_number ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $document->document_number }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $document->package->transaction->no_bukti }}</td>
                                <td class="px-4 py-3">{{ $document->package->transaction->transaction_date?->format('d-m-Y') }}</td>
                                <td class="px-4 py-3"><x-ui.status-badge :status="$document->status" size="xs" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-[var(--ui-fg-muted)]">Belum ada nomor SPJ pada konteks aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-section-card>
    </div>
</x-layouts.tailwind-app>
