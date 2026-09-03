@props([
    'type' => 'text',
    'name' => null,
    'id' => null,
    'value' => null,
    'readonly' => false,
    'disabled' => false,
])

<input
    type="{{ $type }}"
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @if(!is_null($value)) value="{{ $value }}" @endif
    @readonly($readonly)
    @disabled($disabled)
    {{ $attributes->class([
        'min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-[var(--theme-accent)] focus:ring-2 focus:ring-[var(--theme-accent)]/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500',
        'border-slate-200 bg-slate-100 text-slate-600 shadow-none hover:border-slate-200 focus:ring-0' => $readonly,
    ]) }}
>
