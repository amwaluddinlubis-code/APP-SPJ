@props([
    'name' => null,
    'id' => null,
    'disabled' => false,
])

<select
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @disabled($disabled)
    {{ $attributes->class(['min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm outline-none transition hover:border-slate-400 focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[var(--theme-accent)]/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500']) }}
>
    {{ $slot }}
</select>
