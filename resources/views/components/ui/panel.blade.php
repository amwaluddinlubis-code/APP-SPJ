@props([
    'variant' => 'default',
    'title' => null,
    'description' => null,
    'padding' => true,
])

@php
    $variant = in_array($variant, ['default', 'soft', 'deep', 'info', 'informational'], true) ? $variant : 'default';
    $panelClass = 'ui-panel ui-panel-' . $variant;
@endphp

<article {{ $attributes->class([$panelClass]) }}>
    @if ($title || isset($actions))
        <header class="ui-panel-header">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="ui-panel-title">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="ui-panel-description">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </header>
    @endif

    <div @class(['ui-panel-body', 'ui-panel-body-padded' => $padding])>
        {{ $slot }}
    </div>
</article>
