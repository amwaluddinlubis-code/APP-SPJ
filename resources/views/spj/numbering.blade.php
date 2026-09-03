<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php($selectedClosure = $selectedSummary['closure'] ?? null)

    <div class="space-y-6">
        <x-page-header
            kicker="PENOMORAN DOKUMEN SPJ"
            title="Penomoran SPJ per Triwulan"
            description="Periksa kesiapan paket sebelum membuat nomor. Nomor hanya dibuat untuk paket yang sudah lengkap dan siap diproses."
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket'])">Lihat Paket SPJ</x-ui.button>
                @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))
                    <x-ui.button variant="secondary" :href="route('document-number-formats.index')">Atur Format Nomor</x-ui.button>
                @endif
            </x-slot:actions>

            <div class="grid divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-4 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Triwulan" :value="'Triwulan '.$selectedQuarter" hint="Periksa sebelum membuat nomor" />
                <x-stat-item label="Siap diberi nomor" :value="number_format($selectedSummary['ready'] ?? 0, 0, ',', '.')" hint="Paket sudah lengkap dan siap diproses" value-class="text-sky-700" />
                <x-stat-item label="Sudah diberi nomor" :value="number_format($selectedSummary['numbered'] ?? 0, 0, ',', '.')" hint="Paket sudah bernomor atau sudah final" value-class="text-emerald-700" />
                <x-stat-item label="Belum siap" :value="number_format($selectedSummary['blocked'] ?? 0, 0, ',', '.')" hint="Paket belum dibuat atau belum lengkap" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <x-section-card title="Urutan penomoran" description="Ikuti langkah berikut agar nomor dokumen dibuat pada tahap yang benar.">
            <div class="grid gap-3 md:grid-cols-5">
                @foreach([
                    ['1', 'Lengkapi transaksi', 'Lengkapi semua data SPJ'],
                    ['2', 'Buat paket SPJ', 'Buat paket dari transaksi'],
                    ['3', 'Tandai siap', 'Pastikan semua data wajib sudah lengkap'],
                    ['4', 'Periksa triwulan', 'Periksa paket yang siap dan yang masih bermasalah'],
                    ['5', 'Buat nomor', 'Buat nomor dokumen secara berurutan'],
                ] as [$number, $title, $description])
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{{ $number }}</span>
                        <p class="mt-3 text-sm font-bold text-slate-900">{{ $title }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    </div>
                @endforeach
            </div>
        </x-section-card>

        <x-section-card title="Pilih triwulan" description="Pilih triwulan yang ingin diperiksa atau diberi nomor.">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach($quarterSummaries as $quarter => $summary)
                    @php($active = $quarter === $selectedQuarter)
                    @php($closureStatus = strtoupper((string) ($summary['closure']?->status ?? 'OPEN')))
                    <a href="{{ route('spj.numbering-workflow', ['quarter' => $quarter]) }}" class="rounded-2xl border p-4 transition {{ $active ? 'border-indigo-300 bg-indigo-50 shadow-sm ring-2 ring-indigo-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Triwulan {{ $quarter }}</p>
                                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['transactions'], 0, ',', '.') }} transaksi</p>
                            </div>
                            <x-ui.status-badge :status="$closureStatus === 'CLOSED' ? 'LOCKED' : 'ACTIVE'" :label="$closureStatus === 'CLOSED' ? 'Ditutup' : 'Terbuka'" size="xs" />
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-white px-2 py-2"><p class="text-[10px] font-bold uppercase text-slate-400">Siap</p><p class="mt-1 font-bold text-sky-700">{{ $summary['ready'] }}</p></div>
                            <div class="rounded-lg bg-white px-2 py-2"><p class="text-[10px] font-bold uppercase text-slate-400">Bernomor</p><p class="mt-1 font-bold text-emerald-700">{{ $summary['numbered'] }}</p></div>
                            <div class="rounded-lg bg-white px-2 py-2"><p class="text-[10px] font-bold uppercase text-slate-400">Belum siap</p><p class="mt-1 font-bold text-amber-700">{{ $summary['blocked'] }}</p></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-section-card>

        <x-section-card title="Periksa paket yang akan diberi nomor" description="Daftar ini hanya untuk pemeriksaan. Nomor belum dibuat sampai Anda menekan tombol penomoran.">
            @if(($selectedSummary['blocked'] ?? 0) > 0)
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-bold">Penomoran belum dapat dilakukan.</p>
                    <p class="mt-1">Masih ada {{ $selectedSummary['blocked'] }} transaksi yang belum memiliki paket SPJ atau paketnya belum lengkap. Selesaikan terlebih dahulu di Ruang Kerja SPJ.</p>
                </div>
            @elseif($selectedClosure && strtoupper((string) $selectedClosure->status) === 'CLOSED')
                <div class="mb-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800">
                    <p class="font-bold">Triwulan ini sudah ditutup.</p>
                    <p class="mt-1">Nomor baru tidak dapat dibuat sampai administrator membuka kembali triwulan ini.</p>
                </div>
            @else
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-bold">Paket siap diperiksa.</p>
                    <p class="mt-1">Tidak ada paket yang belum lengkap atau transaksi tanpa paket yang menghambat penomoran triwulan ini.</p>
                </div>
            @endif

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table data-pagination="none" class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Bukti / Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Uraian</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status Paket</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Nomor SPJ</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Nilai</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($previewPackages as $package)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><p class="font-mono font-bold text-indigo-700">{{ $package->transaction->no_bukti }}</p><p class="mt-1 text-xs text-slate-500">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td>
                                <td class="max-w-md px-4 py-3"><p class="truncate font-semibold text-slate-800">{{ $package->transaction->payment_description ?: $package->transaction->description ?: 'Uraian belum tersedia' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $package->transaction->recipient_name ?: 'Penerima belum diisi' }}</p></td>
                                <td class="px-4 py-3"><x-ui.status-badge :status="$package->status" /></td>
                                <td class="px-4 py-3"><p class="font-mono text-xs font-bold {{ $package->document_number ? 'text-emerald-700' : 'text-slate-400' }}">{{ $package->document_number ?: 'Belum diberi nomor' }}</p></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-800">{{ $rupiah($package->transaction->gross_amount) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="text-xs font-bold text-indigo-700 hover:underline">Lihat paket →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center"><p class="font-semibold text-slate-700">Belum ada paket yang siap diberi nomor pada triwulan ini.</p><p class="mt-1 text-sm text-slate-500">Lengkapi transaksi, buat paket SPJ, lalu tandai paket sebagai siap diproses.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(auth()->user()->isAdministrator())
                <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-4" data-confirm="Nomor akan dibuat untuk dokumen yang dipilih pada Triwulan {{ $selectedQuarter }}. Dokumen yang sudah memiliki nomor tidak akan dinomori ulang. Lanjutkan?">
                    @csrf
                    <input type="hidden" name="quarter" value="{{ $selectedQuarter }}">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-indigo-950">Pilih dokumen yang akan diberi nomor</p>
                            <p class="mt-1 text-xs text-indigo-700">Nomor dibuat sesuai tanggal dokumen dan format penomoran yang sedang aktif.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($documentTypes as $documentType)
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-900">
                                        <input type="checkbox" name="document_types[]" value="{{ $documentType }}" checked class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ str_replace('_', ' ', $documentType) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <button class="inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50" @disabled(($selectedSummary['blocked'] ?? 0) > 0 || ($selectedClosure && strtoupper((string) $selectedClosure->status) === 'CLOSED'))>
                            Buat Nomor Triwulan {{ $selectedQuarter }}
                        </button>
                    </div>
                </form>
            @else
                <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Anda dapat memeriksa kesiapan paket. Pembuatan nomor secara massal hanya dapat dilakukan oleh administrator.</div>
            @endif
        </x-section-card>

        <x-section-card title="Riwayat penomoran" description="Lihat hasil proses penomoran sebelumnya, termasuk jumlah nomor yang dibuat dan dokumen yang dilewati.">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table data-pagination="none" class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Waktu</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Triwulan</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Status</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Nomor dibuat</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Dilewati</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Catatan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($recentRuns as $run)
                            <tr><td class="px-4 py-3 text-slate-600">{{ $run->started_at?->translatedFormat('d M Y H:i') ?: '—' }}</td><td class="px-4 py-3 font-semibold">Triwulan {{ $run->quarter }}</td><td class="px-4 py-3"><x-ui.status-badge :status="$run->status" size="xs" /></td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $run->numbered_count ?? 0 }}</td><td class="px-4 py-3 text-right font-semibold text-slate-600">{{ $run->skipped_count ?? 0 }}</td><td class="max-w-sm px-4 py-3 text-xs text-slate-500">{{ $run->error_message ?: 'Proses selesai tanpa kendala.' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada riwayat penomoran untuk triwulan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-section-card>
    </div>
</x-layouts.tailwind-app>
