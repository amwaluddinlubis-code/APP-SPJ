@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

<div {{ $attributes->class(['ui-field']) }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="ui-label">
            {{ $label }}
            @if($required)<span class="ui-required" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($error)
        <p class="ui-error">{{ $error }}</p>
    @elseif($hint)
        <p class="ui-hint">{{ $hint }}</p>
    @endif
</div>
