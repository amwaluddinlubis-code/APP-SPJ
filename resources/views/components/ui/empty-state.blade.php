@props(['title' => 'Belum ada data', 'description' => null, 'icon' => '∅'])
<div {{ $attributes->class(['ui-empty']) }}>
    <span class="ui-empty-icon" aria-hidden="true">{{ $icon }}</span>
    <div class="ui-empty-title">{{ $title }}</div>
    @if($description)<div class="ui-empty-description">{{ $description }}</div>@endif
    @isset($actions)<div class="mt-2 flex flex-wrap justify-center gap-2">{{ $actions }}</div>@endisset
</div>
