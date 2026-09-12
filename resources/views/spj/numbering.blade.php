<x-layouts.tailwind-app>
    @php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
    @php($selectedClosure = $selectedSummary['closure'] ?? null)

    <div class="spj-semantic-workspace space-y-6">
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
                @if(auth()->user()->isAdministrator())
                    <x-ui.button variant="danger" :href="route('spj.numbering-correction', ['quarter' => $selectedQuarter])">Koreksi Penomoran</x-ui.button>
                @endif
            </x-slot:actions>

            @php($packagedCount = max(0, ($selectedSummary['transactions'] ?? 0) - ($selectedSummary['without_package'] ?? 0)))
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-5">
                <x-stat-item label="Transaksi ber-rincian" :value="number_format($selectedSummary['transactions'] ?? 0, 0, ',', '.')" :hint="'Triwulan '.$selectedQuarter" />
                <x-stat-item label="Sudah berpaket" :value="number_format($packagedCount, 0, ',', '.')" hint="Transaksi menjadi paket" />
                <x-stat-item label="Siap dinomori" :value="number_format($selectedSummary['ready'] ?? 0, 0, ',', '.')" hint="Lengkap dan siap diproses" value-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Terhambat" :value="number_format($selectedSummary['blocked'] ?? 0, 0, ',', '.')" hint="Belum berpaket atau draft" :value-class="($selectedSummary['blocked'] ?? 0) > 0 ? 'text-amber-700' : 'text-emerald-700'" />
                <x-stat-item label="Bernomor" :value="number_format($selectedSummary['numbered'] ?? 0, 0, ',', '.')" hint="Bernomor atau final" value-class="text-emerald-700" />
            </div>
        </x-page-header>

        <x-section-card title="Pilih triwulan" description="Pilih triwulan yang ingin diperiksa atau diberi nomor.">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach($quarterSummaries as $quarter => $summary)
                    @php($active = $quarter === $selectedQuarter)
                    @php($closureStatus = strtoupper((string) ($summary['closure']?->status ?? 'OPEN')))
                    <a href="{{ route('spj.numbering-workflow', ['quarter' => $quarter]) }}" class="rounded-2xl border p-4 transition {{ $active ? 'border-[var(--theme-accent)] bg-[var(--theme-accent-soft)] shadow-sm ring-2 ring-[var(--theme-accent-soft)]' : 'border-[var(--ui-line)] bg-[var(--ui-surface-base)] hover:border-[var(--ui-line-strong)] hover:bg-[var(--ui-surface-soft)]' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Triwulan {{ $quarter }}</p>
                                <p class="mt-1 text-xl font-bold text-[var(--ui-fg-strong)]">{{ number_format($summary['transactions'], 0, ',', '.') }} transaksi</p>
                            </div>
                            <x-ui.status-badge :status="$closureStatus === 'CLOSED' ? 'LOCKED' : 'ACTIVE'" :label="$closureStatus === 'CLOSED' ? 'Ditutup' : 'Terbuka'" size="xs" />
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-[var(--ui-surface-base)] px-2 py-2"><p class="text-[10px] font-bold uppercase text-[var(--ui-fg-muted)]">Siap</p><p class="mt-1 font-bold text-[var(--theme-content-accent)]">{{ $summary['ready'] }}</p></div>
                            <div class="rounded-lg bg-[var(--ui-surface-base)] px-2 py-2"><p class="text-[10px] font-bold uppercase text-[var(--ui-fg-muted)]">Bernomor</p><p class="mt-1 font-bold text-emerald-700">{{ $summary['numbered'] }}</p></div>
                            <div class="rounded-lg bg-[var(--ui-surface-base)] px-2 py-2"><p class="text-[10px] font-bold uppercase text-[var(--ui-fg-muted)]">Belum siap</p><p class="mt-1 font-bold text-amber-700">{{ $summary['blocked'] }}</p></div>
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
                <div class="mb-4 rounded-xl border border-[var(--ui-line-strong)] bg-[var(--ui-surface-muted)] px-4 py-3 text-sm text-[var(--ui-fg-strong)]">
                    <p class="font-bold">Triwulan ini sudah ditutup.</p>
                    <p class="mt-1">Nomor baru tidak dapat dibuat sampai administrator membuka kembali triwulan ini.</p>
                </div>
            @else
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-bold">Paket siap diperiksa.</p>
                    <p class="mt-1">Tidak ada paket yang belum lengkap atau transaksi tanpa paket yang menghambat penomoran triwulan ini.</p>
                </div>
            @endif

            <div class="overflow-x-auto rounded-xl border border-[var(--ui-line)]">
                <table data-pagination="none" class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Bukti / Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Status Paket</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nomor SPJ</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($pagedPackages as $package)
                            <tr class="hover:bg-[var(--ui-table-row-hover)]">
                                <td class="px-4 py-3"><p class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $package->transaction->no_bukti }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $package->transaction->transaction_date?->translatedFormat('d F Y') }}</p></td>
                                <td class="max-w-md px-4 py-3"><p class="font-semibold text-[var(--ui-fg-strong)]">{{ $package->transaction->payment_description ?: $package->transaction->description ?: 'Uraian belum tersedia' }}</p><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $package->transaction->recipient_name ?: 'Penerima belum diisi' }}</p></td>
                                <td class="px-4 py-3"><x-ui.status-badge :status="$package->status" /></td>
                                <td class="px-4 py-3"><p class="font-mono text-xs font-bold {{ $package->document_number ? 'text-emerald-700' : 'text-[var(--ui-fg-muted)]' }}">{{ $package->document_number ?: 'Belum diberi nomor' }}</p></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($package->transaction->gross_amount) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $package->id]) }}" class="text-xs font-bold text-[var(--theme-content-accent)] hover:underline">Lihat paket →</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-12 text-center"><p class="font-semibold text-[var(--ui-fg)]">Belum ada paket yang siap diberi nomor pada triwulan ini.</p><p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Lengkapi transaksi, buat paket SPJ, lalu tandai paket sebagai siap diproses.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pagedPackages->hasPages())
                <div class="mt-3 flex justify-end">{{ $pagedPackages->links() }}</div>
            @endif

            @if(auth()->user()->isAdministrator())
                <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="mt-5 rounded-2xl border border-[var(--theme-accent-soft)] bg-[var(--theme-accent-soft)] p-4" x-data="{ reviewOpen: false, expandedReviews: [], reviewPage: 1, reviewTotalPages() { return Math.max(1, Math.ceil({{ $previewPackages->count() }} / 5)); }, toggleReview(id) { this.expandedReviews = this.expandedReviews.includes(id) ? this.expandedReviews.filter(item => item !== id) : [...this.expandedReviews, id]; } }">
                    @csrf
                    <input type="hidden" name="quarter" value="{{ $selectedQuarter }}">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-[var(--ui-fg-strong)]">Pilih dokumen yang akan diberi nomor</p>
                            <p class="mt-1 text-xs text-[var(--theme-content-accent)]">Nomor dibuat sesuai tanggal dokumen dan format penomoran yang sedang aktif.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($documentTypes as $documentType)
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--theme-accent-soft)] bg-[var(--ui-surface-base)] px-3 py-2 text-xs font-semibold text-[var(--theme-content-accent)]">
                                        <input type="checkbox" name="document_types[]" value="{{ $documentType }}" checked class="rounded border-[var(--ui-line-strong)] text-[var(--theme-accent)] focus:ring-[var(--theme-accent)]">
                                        <span>{{ $documentLabels[$documentType] ?? $documentType }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <button type="button" @click="reviewOpen = true" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[var(--theme-action-bg)] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[var(--theme-action-hover-bg)] disabled:cursor-not-allowed disabled:opacity-50" @disabled(($selectedSummary['blocked'] ?? 0) > 0 || ($selectedClosure && strtoupper((string) $selectedClosure->status) === 'CLOSED'))>
                            Periksa &amp; Buat Nomor Triwulan {{ $selectedQuarter }}
                        </button>
                    </div>

                    <div x-cloak x-show="reviewOpen" x-transition.opacity class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4" @keydown.escape.window="reviewOpen = false">
                        <div x-show="reviewOpen" x-transition @click.outside="reviewOpen = false" class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-2xl">
                            <div class="flex items-start justify-between gap-4 border-b border-[var(--ui-line)] px-5 py-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">Review sebelum penomoran triwulan {{ $selectedQuarter }}</p>
                                    <h3 class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Periksa isian {{ count($previewPackages) }} paket</h3>
                                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Siap {{ $selectedSummary['ready'] ?? 0 }} · Bernomor {{ $selectedSummary['numbered'] ?? 0 }} · Terhambat {{ $selectedSummary['blocked'] ?? 0 }}. Nomor hanya dibuat untuk paket yang lolos validasi server.</p>
                                </div>
                                <button type="button" @click="reviewOpen = false" class="rounded-md px-2 py-1 text-xl text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]" aria-label="Tutup">×</button>
                            </div>
                            <div class="min-h-0 flex-1 overflow-y-auto">
                                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                                    <thead class="sticky top-0 bg-[var(--ui-surface-soft)]">
                                        <tr>
                                            <th class="w-10 px-2 py-2"><span class="sr-only">Rincian isian</span></th>
                                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Bukti</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian / Penerima</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kategori / Bayar</th>
                                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Status</th>
                                            <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                                        </tr>
                                    </thead>
                                    @forelse($previewPackages as $index => $package)
                                        <tbody x-show="Math.floor({{ $index }} / 5) + 1 === reviewPage" class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                                            <tr class="border-t border-[var(--ui-line)]">
                                                <td class="px-2 py-2 text-center"><button type="button" @click="toggleReview({{ $package->id }})" :aria-expanded="expandedReviews.includes({{ $package->id }}).toString()" :title="expandedReviews.includes({{ $package->id }}) ? 'Sembunyikan isian' : 'Lihat semua isian'" class="inline-flex h-7 w-7 items-center justify-center rounded border border-[var(--ui-line)] text-[var(--ui-fg-muted)] hover:bg-[var(--ui-surface-soft)]"><span x-text="expandedReviews.includes({{ $package->id }}) ? '▾' : '▸'"></span></button></td>
                                                <td class="whitespace-nowrap px-4 py-2"><p class="font-mono text-xs font-bold text-[var(--theme-content-accent)]">{{ $package->transaction->no_bukti }}</p><p class="mt-0.5 text-[11px] text-[var(--ui-fg-muted)]">{{ $package->transaction->transaction_date?->translatedFormat('d M Y') }}</p></td>
                                                <td class="min-w-56 px-4 py-2"><p class="text-xs font-semibold leading-5 text-[var(--ui-fg-strong)]">{{ $package->transaction->payment_description ?: $package->transaction->description ?: 'Uraian belum tersedia' }}</p><p class="mt-0.5 text-[11px] text-[var(--ui-fg-muted)]">{{ $package->transaction->effective_receipt_recipient_name ?: 'Penerima belum diisi' }}</p></td>
                                                <td class="whitespace-nowrap px-4 py-2 text-xs text-[var(--ui-fg)]">{{ $package->transaction->spj_category ?: '—' }} · {{ $package->transaction->payment_method ?: '—' }}</td>
                                                <td class="px-4 py-2"><x-ui.status-badge :status="$package->status" size="xs" /></td>
                                                <td class="whitespace-nowrap px-4 py-2 text-right text-xs font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($package->transaction->gross_amount) }}</td>
                                            </tr>
                                            <tr x-show="expandedReviews.includes({{ $package->id }})" class="bg-[var(--ui-surface-soft)]">
                                                <td></td>
                                                <td colspan="5" class="px-0 py-0">@include('spj.partials.package.numbering-review-detail', ['package' => $package])</td>
                                            </tr>
                                        </tbody>
                                        @empty
                                            <tbody><tr><td colspan="6" class="px-5 py-8 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada paket pada triwulan ini.</td></tr></tbody>
                                        @endforelse
                                </table>
                            </div>
                            <div x-show="reviewTotalPages() > 1" class="flex items-center justify-between gap-3 border-t border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-5 py-2 text-xs">
                                <span class="font-semibold text-[var(--ui-fg-muted)]">Halaman <span x-text="reviewPage"></span> dari <span x-text="reviewTotalPages()"></span> · {{ $previewPackages->count() }} paket · 5 per halaman</span>
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" @click="reviewPage = Math.max(1, reviewPage - 1)" :disabled="reviewPage <= 1" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">‹</button>
                                    <button type="button" @click="reviewPage = Math.min(reviewTotalPages(), reviewPage + 1)" :disabled="reviewPage >= reviewTotalPages()" class="h-8 rounded border border-[var(--ui-line)] px-2 font-bold disabled:opacity-35">›</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4">
                                <p class="text-xs text-[var(--ui-fg-muted)]">Dokumen yang sudah bernomor dilewati otomatis. Batal bila masih ada yang terhambat.</p>
                                <div class="flex gap-2">
                                    <button type="button" @click="reviewOpen = false" class="rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-4 py-2 text-sm font-bold text-[var(--ui-fg)]">Kembali periksa</button>
                                    <button type="submit" class="rounded-lg bg-[var(--theme-action-bg)] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[var(--theme-action-hover-bg)]">Konfirmasi &amp; Buat Nomor</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @else
                <div class="mt-5 rounded-xl border border-[var(--theme-accent-soft)] bg-[var(--theme-accent-soft)] px-4 py-3 text-sm text-[var(--ui-fg-strong)]">Anda dapat memeriksa kesiapan paket. Pembuatan nomor secara massal hanya dapat dilakukan oleh administrator.</div>
            @endif
        </x-section-card>

        <x-section-card title="Riwayat penomoran" description="Lihat hasil proses penomoran sebelumnya, termasuk jumlah nomor yang dibuat dan dokumen yang dilewati.">
            <div class="overflow-x-auto rounded-xl border border-[var(--ui-line)]">
                <table data-pagination="none" class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Waktu</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Triwulan</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Status</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Nomor dibuat</th><th class="px-4 py-3 text-right text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Dilewati</th><th class="px-4 py-3 text-left text-xs font-bold uppercase text-[var(--ui-fg-muted)]">Catatan</th></tr></thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($recentRuns as $run)
                            <tr><td class="px-4 py-3 text-[var(--ui-fg)]">{{ $run->started_at?->translatedFormat('d M Y H:i') ?: '—' }}</td><td class="px-4 py-3 font-semibold">Triwulan {{ $run->quarter }}</td><td class="px-4 py-3"><x-ui.status-badge :status="$run->status" size="xs" /></td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ $run->numbered_count ?? 0 }}</td><td class="px-4 py-3 text-right font-semibold text-[var(--ui-fg)]">{{ $run->skipped_count ?? 0 }}</td><td class="max-w-sm px-4 py-3 text-xs text-[var(--ui-fg-muted)]">{{ $run->error_message ?: 'Proses selesai tanpa kendala.' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-[var(--ui-fg-muted)]">Belum ada riwayat penomoran untuk triwulan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-section-card>
    </div>
</x-layouts.tailwind-app>