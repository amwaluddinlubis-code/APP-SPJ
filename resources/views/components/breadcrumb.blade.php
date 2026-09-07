@props(['items' => [], 'onDark' => false])
<nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}"
        class="{{ $onDark ? 'text-[var(--text-comfort-on-dark-soft)] hover:text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }} transition">
        <span class="sr-only">Home</span>
        <x-ui.icon name="home" size="sm" />
    </a>
    @foreach ($items as $index => $item)
        <x-ui.icon name="chevron-right" size="sm"
            class="{{ $onDark ? 'text-[var(--text-comfort-on-dark-muted)]' : 'text-[var(--ui-line-strong)]' }}" />
        @if (isset($item['url']))
            <a href="{{ $item['url'] }}"
                class="{{ $onDark ? 'text-[var(--text-comfort-on-dark-soft)] hover:text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }} transition">{{ $item['label'] }}</a>
        @else
            <span
                class="font-semibold {{ $onDark ? 'text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-strong)]' }}">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
