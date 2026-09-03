@props(['total' => null])
@php($current = request('perPage', '15'))
<div class="flex items-center gap-2 text-xs">
    <span class="text-slate-500 hidden sm:inline">Baris/halaman:</span>
    <select onchange="const u=new URL(window.location); u.searchParams.set('perPage', this.value); u.searchParams.delete('page'); window.location=u.toString()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200">
        @foreach([15,25,50,100] as $opt)
            <option value="{{ $opt }}" @selected((string)$current===(string)$opt)>{{ $opt }}</option>
        @endforeach
        <option value="all" @selected($current==='all')>All</option>
    </select>
    @if($total!==null)<span class="text-slate-400 hidden lg:inline">• Total {{ number_format($total,0,',','.') }}</span>@endif
</div>
