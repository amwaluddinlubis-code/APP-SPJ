@props([
    'month' => null,
    'quarter' => null,
    'semester' => null,
    'search' => null,
    'status' => null,
    'spjType' => null,
    'state' => null,
    'spjTypes' => [],
    'additionalFilters' => null,
])
@php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
<section class="overflow-hidden rounded-2xl border border-indigo-200 bg-indigo-50 shadow">
    <form method="GET" class="grid gap-3 border-b border-indigo-100 px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-indigo-700" for="filter-month">Bulan</label>
            <select id="filter-month" name="month" class="mt-1.5 w-full rounded-lg border-indigo-200 bg-white px-3 py-2 text-sm">
                <option value="">Semua bulan</option>
                @foreach(range(1, 12) as $option)
                    <option value="{{ $option }}" @selected($month === $option)>{{ \Carbon\Carbon::create()->month($option)->translatedFormat('F') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-indigo-700" for="filter-quarter">Triwulan</label>
            <select id="filter-quarter" name="quarter" class="mt-1.5 w-full rounded-lg border-indigo-200 bg-white px-3 py-2 text-sm">
                <option value="">Semua triwulan</option>
                @foreach(range(1, 4) as $option)
                    <option value="{{ $option }}" @selected($quarter === $option)>Triwulan {{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-indigo-700" for="filter-semester">Semester</label>
            <select id="filter-semester" name="semester" class="mt-1.5 w-full rounded-lg border-indigo-200 bg-white px-3 py-2 text-sm">
                <option value="">Semua semester</option>
                <option value="1" @selected($semester === 1)>Semester 1</option>
                <option value="2" @selected($semester === 2)>Semester 2</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-indigo-700" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="mt-1.5 w-full rounded-lg border-indigo-200 bg-white px-3 py-2 text-sm">
                <option value="">Semua status</option>
                @if($status)
                    @foreach($status as $option)
                        <option value="{{ $option }}" @selected(request('status') === $option)>{{ $option }}</option>
                    @endforeach
                @endif
            </select>
        </div>
        <div class="flex items-end">
            <input type="hidden" name="q" value="{{ $search }}">
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Terapkan Filter</button>
        </div>
        @if($month || $quarter || $semester || $search || $status)
            <a href="{{ request()->url() }}" class="rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700">Reset Periode</a>
        @endif
    </form>
    @if(isset($additionalFilters))
        {{ $additionalFilters }}
    @endif
</section>
