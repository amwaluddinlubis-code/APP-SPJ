@props([
    'type' => 'text',
    'name' => null,
    'id' => null,
    'value' => null,
    'readonly' => false,
    'disabled' => false,
])

<input
    type="{{ $type }}"
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @if(!is_null($value)) value="{{ $value }}" @endif
    @readonly($readonly)
    @disabled($disabled)
    {{ $attributes->class([
        'ui-input',
        'ui-input-readonly' => $readonly,
    ]) }}
>
