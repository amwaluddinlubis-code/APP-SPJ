@props(['tabs' => [], 'activeTab' => null])
<div class="border-b border-slate-200 bg-slate-50/70">
    <nav class="flex gap-1 overflow-x-auto px-2 py-1 text-base" aria-label="Tabs">
        @foreach($tabs as $tab)
            <button
                type="button"
                data-tab="{{ $tab['id'] }}"
                class="tab-btn whitespace-nowrap rounded-md px-3 py-2 text-base font-bold border transition {{ $activeTab === $tab['id'] ? 'bg-white border-slate-200 text-indigo-700 shadow-sm' : 'border-transparent text-slate-600 hover:text-slate-800' }}"
            >
                {{ $tab['label'] }}
                @if(isset($tab['badge']))
                    <span class="ml-1 rounded-full {{ $tab['badgeColor'] ?? 'bg-slate-100' }} px-1.5 py-0.5 text-[11px]">{{ $tab['badge'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>
</div>
