@props(['items' => [], 'onDark' => false])
<nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}"
        class="{{ $onDark ? 'text-[var(--text-comfort-on-dark-soft)] hover:text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }} transition">
        <span class="sr-only">Home</span>
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
    </a>
    @foreach ($items as $index => $item)
        <svg class="h-4 w-4 {{ $onDark ? 'text-[var(--text-comfort-on-dark-muted)]' : 'text-[var(--ui-line-strong)]' }}"
            fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        @if (isset($item['url']))
            <a href="{{ $item['url'] }}"
                class="{{ $onDark ? 'text-[var(--text-comfort-on-dark-soft)] hover:text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]' }} transition">{{ $item['label'] }}</a>
        @else
            <span
                class="font-semibold {{ $onDark ? 'text-[var(--text-comfort-on-dark)]' : 'text-[var(--ui-fg-strong)]' }}">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
