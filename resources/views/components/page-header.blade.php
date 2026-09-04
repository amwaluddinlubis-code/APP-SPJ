@props([
    'title',
    'subtitle' => null,
    'description' => null,
    'kicker' => null,
    'gradient' => 'theme',
    'icon' => null,
])

@php($headerDescription = $subtitle ?: $description)

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
                        <span class="page-header-icon">{{ $icon }}</span>
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
