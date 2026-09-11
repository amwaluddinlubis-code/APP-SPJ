@props(['tabs' => [], 'activeTab' => null])
<div class="ui-tabs">
    <nav class="ui-tabs-list" role="tablist" aria-label="Tabs">
        @foreach($tabs as $tab)
            <button
                type="button"
                id="tab-{{ $tab['id'] }}"
                role="tab"
                aria-controls="panel-{{ $tab['id'] }}"
                aria-selected="{{ $activeTab === $tab['id'] ? 'true' : 'false' }}"
                data-tab="{{ $tab['id'] }}"
                class="ui-tab {{ $activeTab === $tab['id'] ? 'ui-tab-active' : '' }}"
            >
                @if(isset($tab['icon']))
                    <x-ui.icon :name="$tab['icon']" size="sm" />
                @endif
                <span>{{ $tab['label'] }}</span>
                @if(isset($tab['badge']))
                    <x-ui.badge variant="{{ $activeTab === $tab['id'] ? 'theme' : 'neutral' }}">{{ $tab['badge'] }}</x-ui.badge>
                @endif
            </button>
        @endforeach
    </nav>
</div>
