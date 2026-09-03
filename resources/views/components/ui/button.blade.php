@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
    $classes = match ($variant) {
        'secondary' => $base.' border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-300',
        'danger' => $base.' bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500',
        'success' => $base.' bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
        'ghost' => $base.' bg-transparent text-slate-600 shadow-none hover:bg-slate-100 focus:ring-slate-300',
        default => $base.' text-white focus:ring-[var(--theme-accent)]',
    };
@endphp

@if($href)
    <a href="{{ $href }}" @if($variant === 'primary') style="background-color:var(--theme-accent)" @endif {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @if($variant === 'primary') style="background-color:var(--theme-accent)" @endif {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
