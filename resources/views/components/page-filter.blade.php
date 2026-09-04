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
<section class="ui-filter-panel">
    <form method="GET" class="ui-filter-grid">
        <div>
            <label class="ui-filter-label" for="filter-month">Bulan</label>
            <x-ui.select id="filter-month" name="month">
                <option value="">Semua bulan</option>
                @foreach(range(1, 12) as $option)
                    <option value="{{ $option }}" @selected($month === $option)>{{ \Carbon\Carbon::create()->month($option)->translatedFormat('F') }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <label class="ui-filter-label" for="filter-quarter">Triwulan</label>
            <x-ui.select id="filter-quarter" name="quarter">
                <option value="">Semua triwulan</option>
                @foreach(range(1, 4) as $option)
                    <option value="{{ $option }}" @selected($quarter === $option)>Triwulan {{ $option }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <label class="ui-filter-label" for="filter-semester">Semester</label>
            <x-ui.select id="filter-semester" name="semester">
                <option value="">Semua semester</option>
                <option value="1" @selected($semester === 1)>Semester 1</option>
                <option value="2" @selected($semester === 2)>Semester 2</option>
            </x-ui.select>
        </div>
        <div>
            <label class="ui-filter-label" for="filter-status">Status</label>
            <x-ui.select id="filter-status" name="status">
                <option value="">Semua status</option>
                @if($status)
                    @foreach($status as $option)
                        <option value="{{ $option }}" @selected(request('status') === $option)>{{ $option }}</option>
                    @endforeach
                @endif
            </x-ui.select>
        </div>
        <div class="flex items-end gap-2">
            <input type="hidden" name="q" value="{{ $search }}">
            <x-ui.button type="submit" class="w-full">Tampilkan</x-ui.button>
        </div>
        @if($month || $quarter || $semester || $search || $status)
            <div class="flex items-center">
                <a href="{{ request()->url() }}" class="ui-btn ui-btn-secondary">Hapus Saringan</a>
            </div>
        @endif
    </form>
    @if(isset($additionalFilters))
        <div class="border-t border-[var(--ui-line)] p-4">{{ $additionalFilters }}</div>
    @endif
</section>
