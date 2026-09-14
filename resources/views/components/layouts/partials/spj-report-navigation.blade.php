@php
    $isSpjReportRoute = request()->routeIs('spj.*') && request('tab') === 'laporan';
    $activeReportScope = (string) request('paket_laporan', 'bulan');
    $reportScopes = [
        ['key' => 'bulan', 'label' => 'Bulanan', 'icon' => 'calendar'],
        ['key' => 'triwulan', 'label' => 'Tahap & Triwulan', 'icon' => 'report'],
        ['key' => 'semester', 'label' => 'Semester', 'icon' => 'report'],
        ['key' => 'tahunan', 'label' => 'Tahunan', 'icon' => 'calendar'],
    ];
@endphp

<div x-data="{ reportMenuOpen: {{ $isSpjReportRoute ? 'true' : 'false' }} }">
    <button type="button"
        @click="reportMenuOpen = !reportMenuOpen"
        :aria-expanded="reportMenuOpen.toString()"
        aria-controls="nav-spj-reports"
        class="app-nav w-full text-left {{ $isSpjReportRoute ? 'app-nav-active' : '' }}"
        title="Laporan SPJ">
        <x-ui.icon name="report" />
        <span class="nav-label flex-1">Laporan SPJ</span>
        <x-ui.icon name="chevron-down" size="xs" class="transition-transform"
            ::class="reportMenuOpen ? 'rotate-180' : ''" />
    </button>

    <div id="nav-spj-reports" x-show="reportMenuOpen" x-collapse
        class="app-nav-submenu ml-5 space-y-1 border-l pl-2">
        @foreach ($reportScopes as $reportScope)
            <a class="app-nav {{ $isSpjReportRoute && $activeReportScope === $reportScope['key'] ? 'app-nav-active' : '' }}"
                href="{{ route('spj.index', ['tab' => 'laporan', 'paket_laporan' => $reportScope['key']]) }}"
                title="Laporan {{ $reportScope['label'] }}">
                <x-ui.icon :name="$reportScope['icon']" size="xs" />
                <span class="nav-label">{{ $reportScope['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
