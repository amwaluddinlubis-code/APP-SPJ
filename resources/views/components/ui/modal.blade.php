@props(['title' => null, 'show' => false, 'maxWidth' => '32rem'])
<div x-data="{ open: @js((bool) $show) }" x-show="open" x-cloak class="ui-modal-backdrop" @keydown.escape.window="open=false" role="dialog" aria-modal="true">
    <div class="ui-modal" style="max-width: {{ $maxWidth }}" @click.outside="open=false">
        @if($title)
            <div class="ui-modal-header"><h2 class="ui-modal-title">{{ $title }}</h2></div>
        @endif
        <div class="ui-modal-body">{{ $slot }}</div>
        @isset($actions)<div class="ui-modal-actions">{{ $actions }}</div>@endisset
    </div>
</div>
