@props([
    'title',
    'description' => null,
    'collapsible' => false,
    'open' => true,
])

<section
    @if($collapsible)
        x-data="{ open: @js($open) }"
    @endif
    {{ $attributes->class(['ui-form-section']) }}
>
    <div class="ui-form-section-header">
        <div class="ui-form-section-copy">
            <h2 class="ui-form-section-title">{{ $title }}</h2>
            @if($description)<p class="ui-form-section-description">{{ $description }}</p>@endif
        </div>
        <div class="ui-form-section-actions">
            @if(isset($actions)){{ $actions }}@endif
            @if($collapsible)
                <button type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-3 !py-1.5 text-xs" @click="open = !open" :aria-expanded="open.toString()" aria-label="Buka atau tutup panel">
                    <span x-text="open ? 'Tutup panel' : 'Buka panel'"></span>
                    <x-ui.icon name="chevron-down" size="xs" ::class="open ? 'rotate-180' : ''" />
                </button>
            @endif
        </div>
    </div>
    <div class="ui-form-section-body" @if($collapsible) x-show="open" @endif>{{ $slot }}</div>
</section>
