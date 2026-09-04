@props(['tabs' => [], 'activeTab' => null])
<div class="ui-tabs">
    <nav class="ui-tabs-list" aria-label="Tabs">
        @foreach($tabs as $tab)
            <button
                type="button"
                data-tab="{{ $tab['id'] }}"
                class="ui-tab {{ $activeTab === $tab['id'] ? 'ui-tab-active' : '' }}"
            >
                {{ $tab['label'] }}
                @if(isset($tab['badge']))
                    <x-ui.badge variant="{{ $activeTab === $tab['id'] ? 'theme' : 'neutral' }}">{{ $tab['badge'] }}</x-ui.badge>
                @endif
            </button>
        @endforeach
    </nav>
</div>
