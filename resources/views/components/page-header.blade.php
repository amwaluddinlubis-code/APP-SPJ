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
    $canonicalIcons = [
        'save', 'edit', 'pencil', 'trash', 'delete', 'plus', 'add', 'minus', 'search', 'filter',
        'refresh', 'reload', 'download', 'upload', 'arrow-left', 'back', 'arrow-right', 'next',
        'arrow-up', 'arrow-down', 'chevron-left', 'chevron-right', 'chevron-up', 'chevron-down',
        'check', 'success', 'x', 'close', 'eye', 'view', 'printer', 'print', 'document', 'file',
        'database', 'settings', 'gear', 'home', 'user', 'users', 'calendar', 'clock', 'info',
        'warning', 'alert', 'lock', 'unlock', 'external-link', 'menu',
    ];
    $usesCanonicalIcon = is_string($icon) && in_array(strtolower(trim($icon)), $canonicalIcons, true);
@endphp

<section {{ $attributes->class(['page-header-shell']) }}>
    <div class="page-header-main {{ $gradient === 'theme' ? 'theme-header' : 'bg-gradient-to-br '.$gradient }}">
        <div class="page-header-decoration page-header-decoration-top"></div>
        <div class="page-header-decoration page-header-decoration-bottom"></div>

        <div class="page-header-layout">
            <div class="page-header-copy">
                @if($kicker)
                    <p class="page-header-kicker">{{ $kicker }}</p>
                @endif

                <div class="page-header-title-row">
                    @if($icon)
                        <span class="page-header-icon" aria-hidden="true">
                            @if($usesCanonicalIcon)
                                <x-ui.icon :name="$icon" size="lg" />
                            @else
                                {{ $icon }}
                            @endif
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="page-header-title">{{ $title }}</h1>
                        @if($headerDescription)
                            <p class="page-header-description">{{ $headerDescription }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if(isset($actions))
                <div class="page-header-actions">{{ $actions }}</div>
            @endif
        </div>
    </div>

    @if(isset($slot) && trim($slot) !== '')
        <div class="page-header-summary">{{ $slot }}</div>
    @endif
</section>
