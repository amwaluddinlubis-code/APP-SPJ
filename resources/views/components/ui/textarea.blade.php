@props([
    'name' => null,
    'id' => null,
    'rows' => 3,
    'readonly' => false,
    'disabled' => false,
])

<textarea
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    rows="{{ $rows }}"
    @readonly($readonly)
    @disabled($disabled)
    {{ $attributes->class([
        'ui-textarea',
        'ui-input-readonly' => $readonly,
    ]) }}
>{{ $slot }}</textarea>
