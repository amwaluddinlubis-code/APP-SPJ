@props([
    'total' => null,
    'name' => 'perPage',
    'current' => null,
    'options' => [15, 25, 50, 100],
    'allowAll' => false,
    'label' => 'Baris',
])

@php($selected = (string) ($current ?? request($name, '15')))

<div {{ $attributes->class(['ui-toolbar-group flex items-center gap-2 text-xs']) }}>
    <label class="font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</label>
    <select
        aria-label="{{ $label }} per halaman"
        onchange="const u=new URL(window.location); u.searchParams.set('{{ $name }}', this.value); u.searchParams.delete('page'); window.location=u.toString()"
        class="ui-select !min-h-9 !w-auto !py-1.5 !text-xs"
    >
        @foreach($options as $opt)
            <option value="{{ $opt }}" @selected($selected === (string) $opt)>{{ $opt }} baris</option>
        @endforeach
        @if($allowAll)
            <option value="all" @selected($selected === 'all')>Semua</option>
        @endif
    </select>
    @if($total !== null)
        <span class="hidden xl:inline" style="color: var(--ui-fg-muted)">• {{ number_format($total, 0, ',', '.') }} data</span>
    @endif
</div>
