@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'ui-btn ui-btn-'.$variant.' inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold shadow-sm transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-50';
    $classes = match ($variant) {
        'secondary' => $base.' border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900 focus:ring-slate-300',
        'danger' => $base.' border border-rose-600 bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500',
        'success' => $base.' border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
        'ghost' => $base.' border border-transparent bg-transparent text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-300',
        default => $base.' border border-transparent text-white hover:brightness-95 focus:ring-[var(--theme-accent)]',
    };
@endphp

@if($href)
    <a href="{{ $href }}" @if($variant === 'primary') style="background-color:var(--theme-accent)" @endif {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @if($variant === 'primary') style="background-color:var(--theme-accent)" @endif {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
