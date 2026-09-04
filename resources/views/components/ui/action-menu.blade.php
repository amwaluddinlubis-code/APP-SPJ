@props(['label' => 'Aksi'])
<div x-data="{ open:false }" class="ui-action-menu" @keydown.escape.window="open=false">
    <button type="button" class="ui-btn ui-btn-secondary" @click="open=!open" :aria-expanded="open.toString()">{{ $label }} <span aria-hidden="true">⌄</span></button>
    <div x-show="open" x-cloak @click.outside="open=false" class="ui-action-menu-panel">{{ $slot }}</div>
</div>
