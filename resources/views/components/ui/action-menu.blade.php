@props(['label' => 'Aksi'])
<div x-data="{ open:false }" class="ui-action-menu" @keydown.escape.window="open=false">
    <button type="button" class="ui-btn ui-btn-secondary" @click="open=!open" :aria-expanded="open.toString()">
        <x-ui.icon name="menu" size="sm" />
        <span>{{ $label }}</span>
        <x-ui.icon name="chevron-down" size="xs" />
    </button>
    <div x-show="open" x-cloak @click.outside="open=false" class="ui-action-menu-panel">{{ $slot }}</div>
</div>
