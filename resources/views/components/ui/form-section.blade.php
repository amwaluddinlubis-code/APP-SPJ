@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class(['ui-form-section']) }}>
    <div class="ui-form-section-header">
        <div class="ui-form-section-copy">
            <h2 class="ui-form-section-title">{{ $title }}</h2>
            @if($description)<p class="ui-form-section-description">{{ $description }}</p>@endif
        </div>
        @if(isset($actions))<div class="ui-form-section-actions">{{ $actions }}</div>@endif
    </div>
    <div class="ui-form-section-body">{{ $slot }}</div>
</section>
