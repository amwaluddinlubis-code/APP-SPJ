@php
    $isSpjReportRoute = request()->routeIs('spj.*') && request('tab') === 'laporan';
    $isPeriodicReportRoute = $isSpjReportRoute && request('jenis_laporan') === 'periode';
    $activeReportScope = (string) request('paket_laporan', 'bulan');
    $reportScopes = [
        ['key' => 'bulan', 'label' => 'Bulanan', 'icon' => 'calendar'],
        ['key' => 'triwulan', 'label' => 'Triwulan', 'icon' => 'report'],
        ['key' => 'semester', 'label' => 'Semester', 'icon' => 'report'],
        ['key' => 'tahunan', 'label' => 'Tahunan', 'icon' => 'calendar'],
    ];
@endphp

<a class="app-nav {{ $isSpjReportRoute && ! $isPeriodicReportRoute ? 'app-nav-active' : '' }}"
    href="{{ route('spj.index', ['tab' => 'laporan']) }}" title="Laporan SPJ">
    <x-ui.icon name="report" />
    <span class="nav-label">Laporan SPJ</span>
</a>

<div x-data="{ reportMenuOpen: {{ $isPeriodicReportRoute ? 'true' : 'false' }} }">
    <button type="button"
        @click="reportMenuOpen = !reportMenuOpen"
        :aria-expanded="reportMenuOpen.toString()"
        aria-controls="nav-periodic-reports"
        class="app-nav w-full text-left {{ $isPeriodicReportRoute ? 'app-nav-section-active' : '' }}"
        title="Laporan Periode">
        <x-ui.icon name="calendar" />
        <span class="nav-label flex-1">Laporan Periode</span>
        <x-ui.icon name="chevron-down" size="xs" class="transition-transform"
            ::class="reportMenuOpen ? 'rotate-180' : ''" />
    </button>

    <div id="nav-periodic-reports" x-show="reportMenuOpen" x-collapse
        class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
        @foreach ($reportScopes as $reportScope)
            <a class="app-nav {{ $isPeriodicReportRoute && $activeReportScope === $reportScope['key'] ? 'app-nav-active' : '' }}"
                href="{{ route('spj.index', [
                    'tab' => 'laporan',
                    'jenis_laporan' => 'periode',
                    'paket_laporan' => $reportScope['key'],
                ]) }}"
                title="Laporan Periode {{ $reportScope['label'] }}">
                <x-ui.icon :name="$reportScope['icon']" size="xs" />
                <span class="nav-label">{{ $reportScope['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
