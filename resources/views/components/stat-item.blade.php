@props([
    'label',
    'value',
    'hint' => null,
    'valueClass' => 'text-slate-900',
])

<div class="px-5 py-4 sm:px-6">
    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
    <p class="mt-1 text-xl font-bold {{ $valueClass }}">{{ $value }}</p>
    @if($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
