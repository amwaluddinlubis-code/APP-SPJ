@props(['items' => [], 'onDark' => false])
<nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('dashboard') }}" class="{{ $onDark ? 'text-indigo-200 hover:text-white' : 'text-slate-500 hover:text-slate-700' }} transition">
        <span class="sr-only">Home</span>
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
    </a>
    @foreach($items as $index => $item)
        <svg class="h-4 w-4 {{ $onDark ? 'text-indigo-300' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        @if(isset($item['url']))
            <a href="{{ $item['url'] }}" class="{{ $onDark ? 'text-indigo-100 hover:text-white' : 'text-slate-600 hover:text-slate-800' }} transition">{{ $item['label'] }}</a>
        @else
            <span class="font-semibold {{ $onDark ? 'text-white' : 'text-slate-800' }}">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
