@props(['total' => null])
@php($current = request('perPage', '15'))
<div class="ui-toolbar-group text-xs">
    <span class="hidden sm:inline" style="color: var(--ui-fg-muted)">Baris/halaman:</span>
    <select
        onchange="const u=new URL(window.location); u.searchParams.set('perPage', this.value); u.searchParams.delete('page'); window.location=u.toString()"
        class="ui-select !min-h-9 !w-auto !py-1.5 !text-xs"
    >
        @foreach([15,25,50,100] as $opt)
            <option value="{{ $opt }}" @selected((string)$current===(string)$opt)>{{ $opt }}</option>
        @endforeach
        <option value="all" @selected($current==='all')>Semua</option>
    </select>
    @if($total!==null)<span class="hidden lg:inline" style="color: var(--ui-fg-muted)">• Total {{ number_format($total,0,',','.') }}</span>@endif
</div>
