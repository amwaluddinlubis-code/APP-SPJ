<!doctype html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SPJ BOSP Web') }}</title>
    <script>
        (() => {
            const saved = localStorage.getItem('spj-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', theme === 'dark');
        })();
    </script>
    @filamentStyles
    @livewireStyles
    @vite('resources/css/app.css')
</head>
<body class="app-body min-h-full">
<x-toast-notifications />
<div x-data="{
        open: false,
        collapsed: true,
        groups: {
            finance: {{ request()->routeIs('rkas-budget.*', 'transactions.*', 'employees.*', 'students.*', 'taxes.*') ? 'true' : 'false' }},
            documents: {{ request()->routeIs('spj.*', 'reconciliation.*', 'audit-reports.*', 'document-templates.*', 'document-number-formats.*') ? 'true' : 'false' }},
            data: {{ request()->routeIs('synced-data.*', 'arkas.settings*', 'dapodik.*') ? 'true' : 'false' }},
            administration: {{ request()->routeIs('years.*', 'schools.*', 'users.*', 'school-backups.*', 'database-manager.*', 'impersonation.*') ? 'true' : 'false' }}
        },
        init() {
            const savedState = localStorage.getItem('spj-sidebar-collapsed');
            this.collapsed = savedState === null ? true : savedState === 'true';
        },
        setSidebarCollapsed(value) {
            this.collapsed = value;
            localStorage.setItem('spj-sidebar-collapsed', String(value));
        },
        toggleSidebar() {
            this.setSidebarCollapsed(!this.collapsed);
        },
        toggleGroup(group) {
            if (this.collapsed && !this.open) this.setSidebarCollapsed(false);
            this.groups[group] = !this.groups[group];
        }
    }" :class="collapsed ? 'lg:grid-cols-[5.25rem_1fr]' : 'lg:grid-cols-[17rem_1fr]'" class="min-h-screen lg:grid">
    <div x-show="open" @click="open=false" x-transition.opacity class="app-sidebar-overlay fixed inset-0 z-30 backdrop-blur-sm lg:hidden"></div>
    <aside :class="[open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'app-sidebar-collapsed' : '']" class="app-sidebar fixed lg:static inset-y-0 left-0 z-40 w-[17rem] transform overflow-y-auto px-4 py-5 transition-all duration-200 lg:w-auto lg:translate-x-0">
        <div class="mb-8 flex items-center justify-between gap-2">
            <a href="{{ route('dashboard') }}" class="app-sidebar-brand flex min-w-0 items-center gap-3 text-lg font-bold"><span class="app-sidebar-brand-mark grid h-10 w-10 shrink-0 place-items-center">SPJ</span><span x-show="!collapsed || open" x-transition.opacity class="truncate">SPJ BOSP Web</span></a>
            <button type="button" @click="toggleSidebar()" :aria-expanded="(!collapsed).toString()" class="app-sidebar-toggle hidden p-2 lg:inline-flex" :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'" :title="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'"><span x-text="collapsed ? '»' : '«'" class="text-xl leading-none"></span></button>
        </div>
        <nav class="space-y-1 text-base" aria-label="Navigasi utama">
            <a class="app-nav {{ request()->routeIs('dashboard') ? 'app-nav-active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard"><x-ui-icon name="dashboard" /><span x-show="!collapsed || open" x-transition.opacity class="nav-label">Dashboard</span></a>
            <a class="app-nav {{ request()->routeIs('dashboard.v2') ? 'app-nav-active' : '' }}" href="{{ route('dashboard.v2') }}" title="Dashboard v.2"><x-ui-icon name="dashboard" /><span x-show="!collapsed || open" x-transition.opacity class="nav-label">Dashboard v.2</span></a>

            <div class="pt-3">
                <button type="button" @click="toggleGroup('finance')" :aria-expanded="groups.finance.toString()" aria-controls="nav-finance" class="app-nav w-full text-left {{ request()->routeIs('rkas-budget.*', 'transactions.*', 'employees.*', 'students.*', 'taxes.*') ? 'app-nav-section-active' : '' }}" title="Keuangan"><x-ui-icon name="transaction" /><span x-show="!collapsed || open" class="nav-label flex-1">Keuangan</span><span x-show="!collapsed || open" class="text-xs transition-transform" :class="groups.finance ? 'rotate-180' : ''">⌄</span></button>
                <div id="nav-finance" x-show="(!collapsed || open) && groups.finance" x-collapse class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
                    <a class="app-nav {{ request()->routeIs('rkas-budget.*') ? 'app-nav-active' : '' }}" href="{{ route('rkas-budget.index') }}"><x-ui-icon name="budget" /><span class="nav-label">Penganggaran RKAS</span></a>
                    <a class="app-nav {{ request()->routeIs('transactions.*') ? 'app-nav-active' : '' }}" href="{{ route('transactions.index') }}"><x-ui-icon name="transaction" /><span class="nav-label">Transaksi</span></a>
                    <a class="app-nav {{ request()->routeIs('employees.*') ? 'app-nav-active' : '' }}" href="{{ route('employees.index') }}"><x-ui-icon name="employee" /><span class="nav-label">Pegawai</span></a>
                    <a class="app-nav {{ request()->routeIs('students.*') ? 'app-nav-active' : '' }}" href="{{ route('students.index') }}"><x-ui-icon name="employee" /><span class="nav-label">Siswa</span></a>
                    <a class="app-nav {{ request()->routeIs('taxes.*') ? 'app-nav-active' : '' }}" href="{{ route('taxes.index') }}"><x-ui-icon name="tax" /><span class="nav-label">Pajak Sinkronisasi</span></a>
                </div>
            </div>

            <div>
                <button type="button" @click="toggleGroup('documents')" :aria-expanded="groups.documents.toString()" aria-controls="nav-documents" class="app-nav w-full text-left {{ request()->routeIs('spj.*', 'reconciliation.*', 'audit-reports.*', 'document-templates.*', 'document-number-formats.*') ? 'app-nav-section-active' : '' }}" title="Dokumen & Laporan"><x-ui-icon name="document" /><span x-show="!collapsed || open" class="nav-label flex-1">Dokumen & Laporan</span><span x-show="!collapsed || open" class="text-xs transition-transform" :class="groups.documents ? 'rotate-180' : ''">⌄</span></button>
                <div id="nav-documents" x-show="(!collapsed || open) && groups.documents" x-collapse class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
                    <a class="app-nav {{ request()->routeIs('spj.*') && request('tab', 'persiapan') !== 'laporan' ? 'app-nav-active' : '' }}" href="{{ route('spj.index') }}"><x-ui-icon name="document" /><span class="nav-label">Ruang Kerja SPJ</span></a>
                    <a class="app-nav {{ request()->routeIs('reconciliation.*') ? 'app-nav-active' : '' }}" href="{{ route('reconciliation.index') }}"><x-ui-icon name="sync" /><span class="nav-label">Rekonsiliasi</span></a>
                    <a class="app-nav {{ request()->routeIs('spj.numbering-workflow') ? 'app-nav-active' : '' }}" href="{{ route('spj.numbering-workflow') }}"><span class="app-nav-icon">№</span><span class="nav-label">Penomoran SPJ</span></a>
                    <a class="app-nav {{ request()->routeIs('spj.*') && request('tab') === 'laporan' ? 'app-nav-active' : '' }}" href="{{ route('spj.index', ['tab' => 'laporan']) }}"><x-ui-icon name="report" /><span class="nav-label">Laporan SPJ</span></a>
                    <a class="app-nav {{ request()->routeIs('audit-reports.*') ? 'app-nav-active' : '' }}" href="{{ route('audit-reports.index') }}"><x-ui-icon name="audit" /><span class="nav-label">Laporan Audit</span></a>
                    @if(auth()->user()->isAdministrator())<a class="app-nav {{ request()->routeIs('document-templates.*') ? 'app-nav-active' : '' }}" href="{{ route('document-templates.index') }}"><x-ui-icon name="document" /><span class="nav-label">Template Dokumen</span></a>@endif
                    @if(in_array(auth()->user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))<a class="app-nav {{ request()->routeIs('document-number-formats.*') ? 'app-nav-active' : '' }}" href="{{ route('document-number-formats.index') }}"><span class="app-nav-icon">№</span><span class="nav-label">Format Penomoran</span></a>@endif
                </div>
            </div>

            <div>
                <button type="button" @click="toggleGroup('data')" :aria-expanded="groups.data.toString()" aria-controls="nav-data" class="app-nav w-full text-left {{ request()->routeIs('synced-data.*', 'arkas.settings*', 'dapodik.*') ? 'app-nav-section-active' : '' }}" title="Data & Sinkronisasi"><x-ui-icon name="database" /><span x-show="!collapsed || open" class="nav-label flex-1">Data & Sinkronisasi</span><span x-show="!collapsed || open" class="text-xs transition-transform" :class="groups.data ? 'rotate-180' : ''">⌄</span></button>
                <div id="nav-data" x-show="(!collapsed || open) && groups.data" x-collapse class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
                    <a class="app-nav {{ request()->routeIs('synced-data.*') ? 'app-nav-active' : '' }}" href="{{ route('synced-data.index') }}"><x-ui-icon name="database" /><span class="nav-label">Data Hasil Sinkron</span></a>
                    @if(auth()->user()->isAdministrator())<a class="app-nav {{ request()->routeIs('dapodik.*') ? 'app-nav-active' : '' }}" href="{{ route('dapodik.index') }}"><x-ui-icon name="sync" /><span class="nav-label">Integrasi Dapodik</span></a>@endif
                    <form method="post" action="{{ route('arkas.sync') }}" data-confirm="Sinkronisasi akan memperbarui data RKAS dan BKU dari ARKAS. Paket SPJ manual dipertahankan, tetapi data transaksi sumber akan disegarkan. Lanjutkan?">@csrf<input type="hidden" name="confirm_sync" value="1"><button class="app-nav w-full text-left"><x-ui-icon name="sync" /><span class="nav-label">Sinkron Semua ARKAS</span></button></form>
                    @if(auth()->user()->isAdministrator())<a class="app-nav {{ request()->routeIs('arkas.settings*') ? 'app-nav-active' : '' }}" href="{{ route('arkas.settings') }}"><x-ui-icon name="settings" /><span class="nav-label">Integrasi ARKAS</span></a>@endif
                </div>
            </div>

            <div>
                <button type="button" @click="toggleGroup('administration')" :aria-expanded="groups.administration.toString()" aria-controls="nav-administration" class="app-nav w-full text-left {{ request()->routeIs('years.*', 'schools.*', 'users.*', 'school-backups.*', 'database-manager.*', 'impersonation.*') ? 'app-nav-section-active' : '' }}" title="Administrasi"><x-ui-icon name="settings" /><span x-show="!collapsed || open" class="nav-label flex-1">Administrasi</span><span x-show="!collapsed || open" class="text-xs transition-transform" :class="groups.administration ? 'rotate-180' : ''">⌄</span></button>
                <div id="nav-administration" x-show="(!collapsed || open) && groups.administration" x-collapse class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
                    <a class="app-nav {{ request()->routeIs('years.*') ? 'app-nav-active' : '' }}" href="{{ route('years.select') }}"><x-ui-icon name="calendar" /><span class="nav-label">Tahun Anggaran</span></a>
                    @if(auth()->user()->isAdministrator())
                        <a class="app-nav {{ request()->routeIs('schools.settings', 'schools.profile.*') ? 'app-nav-active' : '' }}" href="{{ route('schools.settings') }}"><x-ui-icon name="settings" /><span class="nav-label">Profil Sekolah</span></a>
                        <a class="app-nav {{ request()->routeIs('users.*') ? 'app-nav-active' : '' }}" href="{{ route('users.index') }}"><x-ui-icon name="employee" /><span class="nav-label">Manajemen User</span></a>
                        <a class="app-nav {{ request()->routeIs('school-backups.*') ? 'app-nav-active' : '' }}" href="{{ route('school-backups.index') }}"><x-ui-icon name="archive" /><span class="nav-label">Backup & Pemulihan</span></a>
                        <a class="app-nav {{ request()->routeIs('database-manager.index') ? 'app-nav-active' : '' }}" href="{{ route('database-manager.index') }}"><x-ui-icon name="server" /><span class="nav-label">Database Aktif</span></a>
                        <a class="app-nav app-nav-danger {{ request()->routeIs('database-manager.reset-form') ? 'app-nav-active' : '' }}" href="{{ route('database-manager.reset-form') }}"><span class="app-nav-icon">↺</span><span class="nav-label">Reset Database</span></a>
                        <a class="app-nav {{ request()->routeIs('impersonation.*') ? 'app-nav-active' : '' }}" href="{{ route('impersonation.index') }}"><span class="app-nav-icon">◎</span><span class="nav-label">Uji Sebagai User</span></a>
                    @endif
                </div>
            </div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="app-nav app-nav-danger mt-6 w-full text-left" title="Keluar"><x-ui-icon name="logout" /><span x-show="!collapsed || open" x-transition.opacity class="nav-label">Keluar</span></button></form>
        </nav>
    </aside>
    <main>
        <header class="app-topbar sticky top-0 z-30 flex min-h-18 flex-wrap items-center justify-between gap-3 px-5 py-4">
            <button @click="open=!open" class="app-topbar-menu p-2.5 lg:hidden" aria-label="Toggle menu">☰</button>
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <div class="app-topbar-school min-w-0">
                    <div class="app-topbar-title truncate text-lg font-extrabold leading-tight sm:text-xl">{{ session('active_school_id') ? \App\Models\School::find(session('active_school_id'))?->name : 'Belum memilih sekolah' }}</div>
                </div>
                <div class="app-topbar-meta flex items-center gap-2 text-sm">
                    @if($headerYears->isNotEmpty())
                        <form method="POST" action="{{ route('years.activate') }}" class="inline-flex items-center gap-2">@csrf<label class="sr-only" for="header-fiscal-year">Tahun anggaran aktif</label><select id="header-fiscal-year" name="fiscal_year_id" onchange="this.form.submit()" class="app-topbar-select app-fiscal-year-select px-3 py-2 text-xs font-bold">@foreach($headerYears as $year)<option value="{{ $year->id }}" @selected($activeFiscalYearId === $year->id)>{{ $year->year }} · {{ $year->fundSource?->name ?? $year->fund_source }}</option>@endforeach</select></form>
                    @else
                        <span>Pilih tahun anggaran</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label class="sr-only" for="theme-select">Tema tampilan</label>
                <select id="theme-select" class="app-topbar-select app-theme-select px-3 py-2 text-xs font-bold"><option value="light">☀ Terang</option><option value="dark">◐ Gelap</option><option value="slate">● Slate</option><option value="blue">● Blue</option><option value="indigo">● Indigo</option><option value="violet">● Violet</option><option value="cyan">● Cyan</option><option value="emerald">● Emerald</option><option value="amber">● Amber</option><option value="rose">● Rose</option><option value="fuchsia">● Fuchsia</option></select>
                <span class="app-runtime-badge hidden px-3 py-1 text-xs font-semibold sm:inline-flex">Livewire + Filament</span>
            </div>
        </header>
        <div class="app-main-content mx-auto max-w-screen-2xl p-5 lg:p-8">
            <x-global-breadcrumb />
            @if(session('impersonator_user_id'))
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                    <div>
                        <p class="text-sm font-bold">Mode uji user aktif</p>
                        <p class="text-xs">Anda sedang melihat aplikasi sebagai {{ auth()->user()->name }}. Admin asal: {{ session('impersonator_user_name') }}.</p>
                    </div>
                    <form method="POST" action="{{ route('impersonation.stop') }}">
                        @csrf
                        <button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white shadow hover:bg-amber-700">Kembali sebagai Admin</button>
                    </form>
                </div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
@filamentScripts
@livewireScripts
@vite('resources/js/app.js')
<div id="app-confirm-dialog" class="app-dialog-backdrop fixed inset-0 z-[100] hidden items-center justify-center p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title">
    <div class="app-dialog-panel w-[calc(100%-2rem)] max-w-md overflow-hidden">
        <div class="app-dialog-header border-b px-5 py-4"><h2 id="app-confirm-title" class="app-dialog-title text-lg font-bold">Konfirmasi tindakan</h2></div>
        <div class="px-5 py-5"><p id="app-confirm-message" class="app-dialog-message text-base leading-relaxed"></p></div>
        <div class="app-dialog-actions flex justify-end gap-2 border-t px-5 py-4"><button type="button" id="app-confirm-cancel" class="ui-btn ui-btn-secondary">Batal</button><button type="button" id="app-confirm-accept" class="ui-btn ui-btn-danger">Ya, lanjutkan</button></div>
    </div>
</div>
<script>
    (() => {
        const dialog = document.getElementById('app-confirm-dialog');
        const message = document.getElementById('app-confirm-message');
        const cancel = document.getElementById('app-confirm-cancel');
        const accept = document.getElementById('app-confirm-accept');
        let pendingForm = null;
        let pendingSubmitter = null;

        const close = () => {
            dialog?.classList.add('hidden');
            dialog?.classList.remove('flex');
            pendingForm = null;
            pendingSubmitter = null;
        };

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === 'true') return;
            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter;
            message.textContent = form.dataset.confirm;
            dialog.classList.remove('hidden');
            dialog.classList.add('flex');
            cancel.focus();
        });
        cancel?.addEventListener('click', close);
        dialog?.addEventListener('click', (event) => { if (event.target === dialog) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !dialog?.classList.contains('hidden')) close(); });
        accept?.addEventListener('click', () => {
            if (!pendingForm) return;
            const form = pendingForm;
            const submitter = pendingSubmitter;
            form.dataset.confirmed = 'true';
            dialog.classList.add('hidden');
            dialog.classList.remove('flex');
            form.requestSubmit(submitter || undefined);
        });
    })();

    (() => {
        const select = document.getElementById('theme-select');
        const palettes = ['gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'sky', 'purple', 'pink'];
        palettes.forEach((palette) => {
            if (!select?.querySelector(`option[value="${palette}"]`)) {
                const option = document.createElement('option');
                option.value = palette;
                option.textContent = '● ' + palette[0].toUpperCase() + palette.slice(1);
                select?.append(option);
            }
        });
        if (select) select.value = document.documentElement.dataset.theme || 'light';
    })();
</script>
</body>
</html>