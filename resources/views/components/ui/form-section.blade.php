@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class(['ui-form-section']) }}>
    <div class="ui-form-section-heading">
        <div>
            <h2>{{ $title }}</h2>
            @if($description)<p>{{ $description }}</p>@endif
        </div>
        @if(isset($actions))<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif
    </div>
    <div class="ui-form-section-body">{{ $slot }}</div>
</section>
