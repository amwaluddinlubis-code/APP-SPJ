@props([
    'label',
    'value',
    'hint' => null,
    'valueClass' => 'text-slate-900',
])

<div class="group relative overflow-hidden bg-white px-5 py-4 transition duration-200 hover:bg-slate-50/70 sm:px-6">
    <span class="absolute left-0 top-4 h-8 w-1 rounded-r-full theme-bg opacity-0 transition group-hover:opacity-70"></span>
    <p class="text-[11px] font-bold uppercase tracking-[.08em] text-slate-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-extrabold tracking-tight {{ $valueClass }}">{{ $value }}</p>
    @if($hint)
        <p class="mt-1.5 text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif
</div>
