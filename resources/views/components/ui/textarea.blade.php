@props([
    'name' => null,
    'id' => null,
    'rows' => 3,
    'readonly' => false,
    'disabled' => false,
])

<textarea
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    rows="{{ $rows }}"
    @readonly($readonly)
    @disabled($disabled)
    {{ $attributes->class([
        'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm leading-6 text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[var(--theme-accent)]/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500',
        'border-slate-200 bg-slate-100 text-slate-600 shadow-none hover:border-slate-200 focus:ring-0' => $readonly,
    ]) }}
>{{ $slot }}</textarea>
