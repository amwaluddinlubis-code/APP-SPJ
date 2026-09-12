@props([
    'title',
    'subtitle' => null,
    'description' => null,
    'kicker' => null,
    'gradient' => 'theme',
    'icon' => null,
])

@php
    $headerDescription = $subtitle ?: $description;
    $context = strtolower(trim(($kicker ?? '') . ' ' . ($title ?? '')));
    $resolvedIcon =
        $icon ?:
        match (true) {
            str_contains($context, 'dashboard') || str_contains($context, 'pusat kerja') => 'dashboard',
            str_contains($context, 'rkas') || str_contains($context, 'anggaran') => 'budget',
            str_contains($context, 'transaksi') => 'transaction',
            str_contains($context, 'spj') || str_contains($context, 'dokumen') || str_contains($context, 'template')
                => 'document',
            str_contains($context, 'pegawai') || str_contains($context, 'user') => 'users',
            str_contains($context, 'siswa') => 'users',
            str_contains($context, 'pajak') => 'tax',
            str_contains($context, 'rekonsiliasi') ||
                str_contains($context, 'sinkron') ||
                str_contains($context, 'dapodik')
                => 'sync',
            str_contains($context, 'audit') => 'audit',
            str_contains($context, 'database') => 'database',
            str_contains($context, 'backup') => 'archive',
            str_contains($context, 'sekolah') => 'home',
            str_contains($context, 'tahun') => 'calendar',
            str_contains($context, 'asisten') => 'info',
            default => 'document',
        };
    $canonicalIcons = [
        'save',
        'edit',
        'pencil',
        'trash',
        'delete',
        'plus',
        'add',
        'minus',
        'search',
        'filter',
        'refresh',
        'reload',
        'download',
        'upload',
        'arrow-left',
        'back',
        'arrow-right',
        'next',
        'arrow-up',
        'arrow-down',
        'chevron-left',
        'chevron-right',
        'chevron-up',
        'chevron-down',
        'check',
        'success',
        'x',
        'close',
        'eye',
        'view',
        'printer',
        'print',
        'document',
        'file',
        'database',
        'settings',
        'gear',
        'home',
        'user',
        'users',
        'calendar',
        'clock',
        'info',
        'warning',
        'alert',
        'lock',
        'unlock',
        'external-link',
        'menu',
        'dashboard',
        'budget',
        'transaction',
        'tax',
        'report',
        'audit',
        'database',
        'sync',
        'archive',
        'server',
        'users',
        'work',
        'number',
        'priority',
        'progress',
        'queue',
        'system',
        'inbox',
        'home',
    ];
    $usesCanonicalIcon = is_string($resolvedIcon) && in_array(strtolower(trim($resolvedIcon)), $canonicalIcons, true);
@endphp

<section {{ $attributes->class(['page-header-shell']) }}>
    <div class="page-header-main {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br ' . $gradient }}">
        <div class="page-header-decoration page-header-decoration-top"></div>
        <div class="page-header-decoration page-header-decoration-bottom"></div>

        <div class="page-header-layout">
            <div class="page-header-copy">
                @if ($kicker)
                    <p class="page-header-kicker">{{ $kicker }}</p>
                @endif

                <div class="page-header-title-row">
                    @if ($resolvedIcon)
                        <span class="page-header-icon" aria-hidden="true">
                            @if ($usesCanonicalIcon)
                                <x-ui.icon :name="$resolvedIcon" size="lg" />
                            @else
                                {{ $resolvedIcon }}
                            @endif
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="page-header-title">{{ $title }}</h1>
                        @if ($headerDescription)
                            <p class="page-header-description">{{ $headerDescription }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if (isset($actions))
                <div class="page-header-actions">{{ $actions }}</div>
            @endif
        </div>
    </div>

    @if (isset($slot) && trim($slot) !== '')
        <div class="page-header-summary">{{ $slot }}</div>
    @endif
</section>
