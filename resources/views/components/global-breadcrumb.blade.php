@php
    $group = null;
    $groupUrl = null;
    $page = null;
    $pageUrl = null;
    $detail = null;

    if (request()->routeIs('dashboard')) {
        $page = 'Beranda';
    } elseif (request()->routeIs('dashboard.v2')) {
        $page = 'Ringkasan Lengkap';
    } elseif (request()->routeIs('rkas-budget.*')) {
        $group = 'Keuangan';
        $page = 'Rencana Anggaran (RKAS)';
    } elseif (request()->routeIs('transactions.*')) {
        $group = 'Keuangan';
        $page = 'Transaksi';
        $pageUrl = route('transactions.index');
        $detail = request()->routeIs('transactions.show') ? 'Rincian Transaksi' : null;
    } elseif (request()->routeIs('employees.*')) {
        $group = 'Keuangan';
        $page = 'Pegawai';
        $pageUrl = route('employees.index');
        $detail = match (true) {
            request()->routeIs('employees.create') => 'Tambah Pegawai',
            request()->routeIs('employees.edit') => 'Ubah Pegawai',
            request()->routeIs('employees.show') => 'Rincian Pegawai',
            default => null,
        };
    } elseif (request()->routeIs('students.*')) {
        $group = 'Keuangan';
        $page = 'Siswa';
        $pageUrl = route('students.index');
        $detail = match (true) {
            request()->routeIs('students.create') => 'Tambah Siswa',
            request()->routeIs('students.edit') => 'Ubah Siswa',
            request()->routeIs('students.show') => 'Rincian Siswa',
            default => null,
        };
    } elseif (request()->routeIs('taxes.*')) {
        $group = 'Keuangan';
        $page = 'Pajak';
    } elseif (request()->routeIs('reconciliation.*')) {
        $group = 'Dokumen & Laporan';
        $page = 'Periksa Perubahan ARKAS';
    } elseif (request()->routeIs('spj.numbering-workflow')) {
        $group = 'Dokumen & Laporan';
        $page = 'Pekerjaan SPJ';
        $pageUrl = route('spj.index');
        $detail = 'Penomoran SPJ';
    } elseif (request()->routeIs('spj.*')) {
        $group = 'Dokumen & Laporan';
        $page = request('tab') === 'laporan' ? 'Laporan SPJ' : 'Pekerjaan SPJ';
    } elseif (request()->routeIs('audit-reports.*')) {
        $group = 'Dokumen & Laporan';
        $page = 'Laporan Pemeriksaan';
    } elseif (request()->routeIs('document-templates.*')) {
        $group = 'Dokumen & Laporan';
        $page = 'Template Dokumen';
    } elseif (request()->routeIs('document-number-formats.*')) {
        $group = 'Dokumen & Laporan';
        $page = 'Format Nomor Dokumen';
    } elseif (request()->routeIs('synced-data.*')) {
        $group = 'Data & Sinkronisasi';
        $page = 'Data Hasil Sinkronisasi';
        $pageUrl = route('synced-data.index');
        if (request()->routeIs('synced-data.show')) {
            $type = strtolower((string) request()->route('type'));
            $detail = match ($type) {
                'rkas' => 'Data RKAS',
                'bku' => 'Data BKU',
                default => 'Rincian Data',
            };
        }
    } elseif (request()->routeIs('arkas.settings*')) {
        $group = 'Data & Sinkronisasi';
        $page = 'Pengaturan ARKAS';
    } elseif (request()->routeIs('dapodik.*')) {
        $group = 'Data & Sinkronisasi';
        $page = 'Pengaturan Dapodik';
    } elseif (request()->routeIs('years.*')) {
        $group = 'Administrasi';
        $page = 'Tahun Anggaran';
    } elseif (request()->routeIs('schools.settings', 'schools.profile.*', 'schools.letterhead')) {
        $group = 'Administrasi';
        $page = 'Profil Sekolah';
        $pageUrl = route('schools.settings');
        $detail = request()->routeIs('schools.letterhead') ? 'Kop Surat' : null;
    } elseif (request()->routeIs('users.*')) {
        $group = 'Administrasi';
        $page = 'Kelola Pengguna';
    } elseif (request()->routeIs('school-backups.*')) {
        $group = 'Administrasi';
        $page = 'Cadangan & Pemulihan';
    } elseif (request()->routeIs('database-manager.*')) {
        $group = 'Administrasi';
        $page = 'Penyimpanan Data Aktif';
    } elseif (request()->routeIs('impersonation.*')) {
        $group = 'Administrasi';
        $page = 'Lihat Sebagai Pengguna';
    }

    $items = array_values(
        array_filter([
            $group ? ['label' => $group, 'url' => $groupUrl] : null,
            $page ? ['label' => $page, 'url' => $detail ? $pageUrl : null] : null,
            $detail ? ['label' => $detail, 'url' => null] : null,
        ]),
    );
@endphp

<style>
    :root {
        --app-sticky-header-height: 72px;
    }

    main nav[aria-label="Breadcrumb"]:not(.app-global-breadcrumb) {
        display: none !important;
    }

    .app-global-breadcrumb {
        position: sticky;
        top: calc(var(--app-sticky-header-height) + .5rem);
        z-index: 25;
    }
</style>

<nav class="app-global-breadcrumb module-breadcrumb mb-4 flex min-w-0 flex-wrap items-center gap-1.5 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)]/95 px-3 py-2 text-sm shadow-sm backdrop-blur-md"
    aria-label="Lokasi halaman">
    @if (request()->routeIs('dashboard'))
        <span
            class="inline-flex items-center gap-2 rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-1.5 font-semibold text-[var(--ui-fg-strong)]"
            aria-current="page">
            <svg class="h-4 w-4 text-[var(--ui-fg-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Beranda
        </span>
    @else
        <a href="{{ route('dashboard') }}"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 font-medium text-[var(--ui-fg-muted)] transition hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]"
            title="Kembali ke beranda">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Beranda</span>
        </a>

        @foreach ($items as $item)
            <svg class="h-3.5 w-3.5 shrink-0 text-[var(--ui-line-strong)]" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            @if (!empty($item['url']))
                <a href="{{ $item['url'] }}"
                    class="rounded-lg px-2 py-1.5 font-medium text-[var(--ui-fg-muted)] transition hover:bg-[var(--ui-surface-soft)] hover:text-[var(--ui-fg-strong)]">{{ $item['label'] }}</a>
            @elseif($loop->last)
                <span
                    class="max-w-full truncate rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-2.5 py-1.5 font-semibold text-[var(--ui-fg-strong)]"
                    aria-current="page">{{ $item['label'] }}</span>
            @else
                <span class="px-1 py-1.5 font-medium text-[var(--ui-fg-muted)]">{{ $item['label'] }}</span>
            @endif
        @endforeach
    @endif
</nav>

<script>
    (() => {
        const header = document.querySelector('main > header');
        if (!header) return;

        const syncStickyOffset = () => {
            document.documentElement.style.setProperty('--app-sticky-header-height',
                `${Math.ceil(header.getBoundingClientRect().height)}px`);
        };

        syncStickyOffset();
        window.addEventListener('resize', syncStickyOffset, {
            passive: true
        });

        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(syncStickyOffset);
            observer.observe(header);
        }
    })();
</script>
