@props(['title' => 'Tindakan berisiko', 'description' => null])
<section {{ $attributes->class(['ui-danger-zone']) }}>
    <div class="ui-danger-zone-header">
        <h2 class="ui-danger-zone-title">{{ $title }}</h2>
        @if($description)<p class="ui-danger-zone-description">{{ $description }}</p>@endif
    </div>
    <div class="ui-danger-zone-body">{{ $slot }}</div>
</section>
