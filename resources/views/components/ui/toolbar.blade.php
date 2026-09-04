<div {{ $attributes->class(['ui-toolbar']) }}>
    <div class="ui-toolbar-group">{{ $slot }}</div>
    @isset($actions)<div class="ui-toolbar-group">{{ $actions }}</div>@endisset
</div>
