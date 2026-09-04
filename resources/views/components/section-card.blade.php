@props([
    'title',
    'description' => null,
    'padding' => true,
])

<section {{ $attributes->class(['ui-section-card']) }}>
    <div class="ui-section-card-header">
        <span class="ui-section-card-accent"></span>

        <div class="ui-section-card-copy">
            <h2 class="ui-section-card-title">{{ $title }}</h2>
            @if($description)
                <p class="ui-section-card-description">{{ $description }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="ui-section-card-actions">{{ $actions }}</div>
        @endif
    </div>

    <div @class(['ui-section-card-body', 'ui-section-card-body-padded' => $padding])>
        {{ $slot }}
    </div>
</section>
