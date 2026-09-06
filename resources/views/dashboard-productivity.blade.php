<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header
            title="Pusat Kerja Operator"
            subtitle="Selesaikan pekerjaan yang sudah dimulai, lanjutkan transaksi berikutnya, lalu masuk ke penomoran tanpa kehilangan konteks."
            kicker="Dashboard Produktivitas"
        >
            <x-slot:actions>
                <x-ui.button :href="route('transactions.index')">
                    <x-ui-icon name="transaction" class="h-4 w-4" />
                    <span>Semua Transaksi</span>
                </x-ui.button>
                <x-ui.button :href="route('dashboard.operational')" variant="secondary">
                    <x-ui-icon name="dashboard" class="h-4 w-4" />
                    <span>Dashboard Lama</span>
                </x-ui.button>
            </x-slot:actions>

            <div class="grid gap-px bg-[var(--ui-line)] sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="bg-[var(--ui-surface-base)] px-5 py-4 transition hover:bg-[var(--ui-surface-soft)]">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Belum Dikerjakan</p>
                    <p class="mt-1 text-3xl font-extrabold text-[var(--ui-fg-strong)]">{{ number_format($productivity['unworked'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Belum memiliki paket SPJ</p>
                </a>
                <a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'draft']) }}" class="bg-[var(--ui-surface-base)] px-5 py-4 transition hover:bg-[var(--ui-surface-soft)]">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Sedang Dikerjakan</p>
                    <p class="mt-1 text-3xl font-extrabold text-amber-800">{{ number_format($productivity['in_progress'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Paket draft belum siap</p>
                </a>
                <a href="{{ route('spj.numbering-workflow') }}" class="bg-[var(--ui-surface-base)] px-5 py-4 transition hover:bg-[var(--ui-surface-soft)]">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">Siap Dinomori</p>
                    <p class="mt-1 text-3xl font-extrabold text-[var(--theme-content-accent)]">{{ number_format($productivity['ready'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Siap masuk workflow penomoran</p>
                </a>
                <a href="{{ route('spj.numbering-workflow') }}" class="bg-[var(--ui-surface-base)] px-5 py-4 transition hover:bg-[var(--ui-surface-soft)]">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Belum Bernomor</p>
                    <p class="mt-1 text-3xl font-extrabold text-[var(--ui-fg-strong)]">{{ number_format($productivity['not_numbered'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Belum selesai sampai tahap nomor</p>
                </a>
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="grid gap-0 lg:grid-cols-[1.25fr_.75fr]">
                <div class="relative overflow-hidden bg-gradient-to-br from-indigo-950 via-indigo-900 to-violet-900 px-6 py-7 text-white lg:px-8 lg:py-8">
                    <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="relative">
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-200">{{ $priority['eyebrow'] }}</p>
                        <h2 class="mt-3 max-w-3xl text-2xl font-extrabold leading-tight sm:text-3xl">{{ $priority['title'] }}</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-indigo-100">{{ $priority['description'] }}</p>
                        <a href="{{ $priority['url'] }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--ui-surface-base)] px-4 py-2.5 text-sm font-extrabold text-indigo-950 shadow-sm transition hover:bg-indigo-50">
                            {{ $priority['action'] }} →
                        </a>
                    </div>
                </div>

                <div class="border-t border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-6 lg:border-l lg:border-t-0">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[var(--ui-fg-muted)]">Progres Keseluruhan</p>
                    <div class="mt-3 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-4xl font-extrabold text-[var(--ui-fg-strong)]">{{ $productivity['completion_percent'] }}%</p>
                            <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Sudah bernomor atau final</p>
                        </div>
                        <p class="text-right text-xs font-semibold text-[var(--ui-fg-muted)]">{{ $productivity['completed'] }} dari {{ $productivity['workflow_total'] }} transaksi</p>
                    </div>
                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-[var(--ui-line)]">
                        <div class="h-full rounded-full bg-[var(--theme-accent)] transition-all" style="width: {{ $productivity['completion_percent'] }}%"></div>
                    </div>
                    <div class="mt-5 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-xl bg-[var(--ui-surface-base)] p-3"><p class="font-extrabold text-[var(--ui-fg-strong)]">{{ $productivity['unworked'] }}</p><p class="mt-1 text-[var(--ui-fg-muted)]">Belum Dikerjakan</p></div>
                        <div class="rounded-xl bg-[var(--ui-surface-base)] p-3"><p class="font-extrabold text-amber-800">{{ $productivity['in_progress'] }}</p><p class="mt-1 text-[var(--ui-fg-muted)]">Sedang Dikerjakan</p></div>
                        <div class="rounded-xl bg-[var(--ui-surface-base)] p-3"><p class="font-extrabold text-[var(--theme-content-accent)]">{{ $productivity['ready'] }}</p><p class="mt-1 text-[var(--ui-fg-muted)]">Siap Dinomori</p></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <header class="border-b border-[var(--ui-line)] px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Lanjutkan Pekerjaan</p>
                    <h2 class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Selesaikan yang sudah dimulai</h2>
                </header>
                <div class="p-5">
                    @if($nextDraftTransaction)
                        <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-bold text-[var(--theme-content-accent)]">{{ $nextDraftTransaction->no_bukti ?: 'Tanpa nomor bukti' }}</p>
                                    <p class="mt-1 line-clamp-2 font-semibold text-[var(--ui-fg-strong)]">{{ $nextDraftTransaction->payment_description ?: $nextDraftTransaction->description ?: 'Uraian belum tersedia' }}</p>
                                    <p class="mt-2 text-xs text-[var(--ui-fg-muted)]">{{ optional($nextDraftTransaction->transaction_date)->format('d/m/Y') }} · {{ $nextDraftTransaction->items_count }} rincian · Rp {{ number_format((float) $nextDraftTransaction->gross_amount, 0, ',', '.') }}</p>
                                </div>
                                <x-ui.status-badge status="DRAFT" size="xs" />
                            </div>
                            <a href="{{ route('spj.checklist', $nextDraftTransaction->spjPackage->id) }}" class="mt-4 inline-flex text-sm font-bold text-[var(--theme-content-accent)]">Lanjutkan sampai siap dinomori →</a>
                        </div>
                    @else
                        <x-ui.empty-state title="Tidak ada pekerjaan draft" description="Tidak ada transaksi yang sedang dikerjakan tetapi belum siap dinomori." />
                    @endif
                </div>
            </article>

            <article class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <header class="border-b border-[var(--ui-line)] px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--theme-content-accent)]">Transaksi Berikutnya</p>
                    <h2 class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Ambil satu pekerjaan baru tanpa mencari manual</h2>
                </header>
                <div class="p-5">
                    @if($nextUnworkedTransaction)
                        <div class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4">
                            <p class="font-mono text-sm font-bold text-[var(--theme-content-accent)]">{{ $nextUnworkedTransaction->no_bukti ?: 'Tanpa nomor bukti' }}</p>
                            <p class="mt-1 line-clamp-2 font-semibold text-[var(--ui-fg-strong)]">{{ $nextUnworkedTransaction->payment_description ?: $nextUnworkedTransaction->description ?: 'Uraian belum tersedia' }}</p>
                            <p class="mt-2 text-xs text-[var(--ui-fg-muted)]">{{ optional($nextUnworkedTransaction->transaction_date)->format('d/m/Y') }} · {{ $nextUnworkedTransaction->items_count }} rincian · Rp {{ number_format((float) $nextUnworkedTransaction->gross_amount, 0, ',', '.') }}</p>
                            <div class="mt-3 inline-flex rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2.5 py-1 text-[11px] font-bold text-[var(--ui-fg-muted)]">Belum Dikerjakan</div>
                            <a href="{{ route('transactions.show', $nextUnworkedTransaction->id) }}#modul-buat-spj" class="mt-4 block text-sm font-bold text-[var(--theme-content-accent)]">Mulai Siapkan SPJ →</a>
                        </div>
                    @else
                        <x-ui.empty-state title="Tidak ada transaksi Belum Dikerjakan" description="Semua transaksi yang memiliki rincian sudah pernah masuk ke workflow SPJ." />
                    @endif
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <header class="flex flex-col gap-2 border-b border-[var(--ui-line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Alur Kerja Operator</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Setiap angka adalah antrean nyata yang perlu bergerak ke tahap berikutnya.</p>
                </div>
                @if($productivity['not_numbered'] > 0)
                    <span class="inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{ $productivity['not_numbered'] }} transaksi belum bernomor</span>
                @endif
            </header>
            <div class="grid gap-px bg-[var(--ui-line)] md:grid-cols-5">
                @php
                    $workflowStages = [
                        ['label' => 'Belum Dikerjakan', 'count' => $productivity['unworked'], 'hint' => 'Mulai isi SPJ'],
                        ['label' => 'Sedang Dikerjakan', 'count' => $productivity['in_progress'], 'hint' => 'Lengkapi checklist'],
                        ['label' => 'Siap Dinomori', 'count' => $productivity['ready'], 'hint' => 'Masuk penomoran'],
                        ['label' => 'Sudah Bernomor', 'count' => $productivity['numbered'], 'hint' => 'Tinjau dokumen'],
                        ['label' => 'Final', 'count' => $productivity['final'], 'hint' => 'Selesai'],
                    ];
                @endphp
                @foreach($workflowStages as $index => $stage)
                    <div class="relative bg-[var(--ui-surface-base)] p-5">
                        <div class="flex items-center justify-between gap-3">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-[var(--ui-surface-soft)] text-xs font-extrabold text-[var(--ui-fg-strong)]">{{ $index + 1 }}</span>
                            @if($index < count($workflowStages) - 1)
                                <span class="text-[var(--ui-fg-muted)]">→</span>
                            @endif
                        </div>
                        <p class="mt-4 text-2xl font-extrabold text-[var(--ui-fg-strong)]">{{ $stage['count'] }}</p>
                        <p class="mt-1 text-sm font-bold text-[var(--ui-fg-strong)]">{{ $stage['label'] }}</p>
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $stage['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.3fr_.7fr]">
            <article class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <div class="border-b border-[var(--ui-line)] px-5 py-4">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Antrean kerja terdekat</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Maksimal delapan transaksi yang masih membutuhkan tindakan operator.</p>
                </div>
                <div class="divide-y divide-[var(--ui-line)]">
                    @forelse($workQueue as $transaction)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('transactions.show', $transaction->id) }}" class="font-mono text-sm font-bold text-[var(--theme-content-accent)]">{{ $transaction->no_bukti ?: 'Tanpa nomor bukti' }}</a>
                                    @if(strtoupper((string) $transaction->source_status) === 'SOURCE_MISSING')
                                        <x-ui.status-badge status="SOURCE_MISSING" size="xs" />
                                    @elseif((bool) $transaction->requires_reconciliation)
                                        <x-ui.status-badge status="REQUIRES_RECONCILIATION" size="xs" />
                                    @elseif($transaction->spjPackage)
                                        <x-ui.status-badge :status="$transaction->spjPackage->status" size="xs" />
                                    @else
                                        <span class="inline-flex rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-2 py-0.5 text-[11px] font-bold text-[var(--ui-fg-muted)]">Belum Dikerjakan</span>
                                    @endif
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->payment_description ?: $transaction->description ?: 'Uraian belum tersedia' }}</p>
                                <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->items_count }} rincian · Rp {{ number_format((float) $transaction->gross_amount, 0, ',', '.') }}</p>
                                <p class="mt-2 text-xs font-semibold text-amber-800">{{ $transaction->next_step }}</p>
                            </div>
                            <a href="{{ $transaction->next_step_url }}" class="inline-flex shrink-0 rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2 text-xs font-bold text-[var(--ui-fg)] transition hover:bg-[var(--ui-surface-soft)]">Kerjakan →</a>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada transaksi yang sedang menunggu tindakan operator.</div>
                    @endforelse
                </div>
            </article>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Penomoran</p>
                    <p class="mt-2 text-3xl font-extrabold text-[var(--ui-fg-strong)]">{{ $productivity['not_numbered'] }}</p>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">transaksi belum mencapai tahap bernomor.</p>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><span class="text-[var(--ui-fg-muted)]">Siap sekarang</span><strong class="text-[var(--theme-content-accent)]">{{ $productivity['ready'] }}</strong></div>
                        <div class="flex items-center justify-between"><span class="text-[var(--ui-fg-muted)]">Masih draft</span><strong class="text-amber-800">{{ $productivity['in_progress'] }}</strong></div>
                        <div class="flex items-center justify-between"><span class="text-[var(--ui-fg-muted)]">Belum Dikerjakan</span><strong class="text-[var(--ui-fg-strong)]">{{ $productivity['unworked'] }}</strong></div>
                    </div>
                    <x-ui.button class="mt-5 w-full" :href="route('spj.numbering-workflow')" variant="secondary">Buka Penomoran SPJ</x-ui.button>
                </section>

                <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Kondisi Sistem</p>
                    @if($productivity['attention'] > 0)
                        <div class="mt-3 rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900">
                            <strong>{{ $productivity['attention'] }} transaksi perlu perhatian.</strong>
                            <p class="mt-1 text-xs">Periksa rekonsiliasi atau data sumber sebelum melanjutkan.</p>
                        </div>
                    @else
                        <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                            <strong>Tidak ada blocker sumber utama.</strong>
                            <p class="mt-1 text-xs">Operator dapat fokus pada antrean kerja SPJ.</p>
                        </div>
                    @endif
                    @if($latestSync)
                        <div class="mt-4"><x-ui.status-badge :status="$latestSync->status" size="xs" /></div>
                    @endif
                </section>
            </aside>
        </section>
    </div>
</x-layouts.tailwind-app>
