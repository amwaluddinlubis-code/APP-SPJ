@props([
    'name' => null,
    'id' => null,
    'disabled' => false,
])

<select
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @disabled($disabled)
    {{ $attributes->class(['ui-select']) }}
>
    {{ $slot }}
</select>
