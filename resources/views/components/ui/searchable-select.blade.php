@props([
    'options' => [],
    'value' => '',
    'name' => null,
    'id' => null,
    'wireModel' => null,
    'placeholder' => 'Pilih opsi',
    'searchPlaceholder' => 'Cari opsi...',
    'required' => false,
])

@php
    $normalizedOptions = collect($options)->map(function ($label, $optionValue) {
        if (is_array($label) && array_key_exists('value', $label)) {
            return ['value' => (string) $label['value'], 'label' => (string) ($label['label'] ?? $label['value'])];
        }

        return ['value' => (string) $optionValue, 'label' => (string) $label];
    })->values()->all();
    $selectedValue = old($name, $value);
    $selectedExpression = $wireModel
        ? '$wire.entangle('.json_encode($wireModel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT).').live'
        : json_encode((string) $selectedValue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $optionsExpression = json_encode($normalizedOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $placeholderExpression = json_encode($placeholder, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<div x-data="{
    open: false,
    search: '',
    selected: {{ $selectedExpression }},
    options: {{ $optionsExpression }},
    get selectedLabel() {
        return this.options.find(option => option.value === String(this.selected))?.label || {{ $placeholderExpression }};
    },
    get filteredOptions() {
        return this.options.filter(option => option.label.toLowerCase().includes(this.search.toLowerCase()));
    },
    choose(value) {
        this.selected = String(value);
        this.open = false;
        this.search = '';
    }
}" class="ui-searchable-select relative">
    <button type="button" id="{{ $id }}" class="ui-searchable-select-trigger {{ $attributes->get('class') }}"
        @click="open = !open" @keydown.escape.window="open = false" :aria-expanded="open.toString()"
        aria-haspopup="listbox" @if($required) aria-required="true" @endif>
        <span class="truncate text-left" x-text="selectedLabel">{{ $placeholder }}</span>
        <x-ui.icon name="chevron-down" size="sm" ::class="open ? 'rotate-180' : ''" />
    </button>
    @if ($name)
        <input type="hidden" name="{{ $name }}" x-model="selected" @if($required) required @endif>
    @endif
    <div x-show="open" x-cloak x-transition.origin.top @click.outside="open = false"
        class="ui-searchable-select-menu" role="listbox" aria-label="{{ $placeholder }}">
        <div class="ui-searchable-select-search-wrap">
            <x-ui.icon name="search" size="sm" class="ui-searchable-select-search-icon" />
            <input type="search" x-model="search" placeholder="{{ $searchPlaceholder }}"
                class="ui-searchable-select-search" @click.stop>
        </div>
        <div class="ui-searchable-select-options">
            <template x-for="option in filteredOptions" :key="option.value">
                <button type="button" role="option" class="ui-searchable-select-option"
                    :aria-selected="String(selected) === option.value" @click="choose(option.value)">
                    <span x-text="option.label"></span>
                    <x-ui.icon name="check" size="xs" x-show="String(selected) === option.value" />
                </button>
            </template>
            <p x-show="filteredOptions.length === 0" class="ui-searchable-select-empty">Tidak ditemukan.</p>
        </div>
    </div>
</div>
