@props([
    'title' => 'Belum ada data',
    'description' => null,
    'icon' => 'document',
])

<div {{ $attributes->class(['ui-empty']) }}>
    <span class="ui-empty-icon" aria-hidden="true">
        <x-ui.icon :name="$icon" size="lg" />
    </span>
    <div class="ui-empty-title">{{ $title }}</div>
    @if($description)<div class="ui-empty-description">{{ $description }}</div>@endif
    @isset($actions)<div class="mt-2 flex flex-wrap justify-center gap-2">{{ $actions }}</div>@endisset
</div>
