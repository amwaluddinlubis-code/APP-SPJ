@props(['message' => null, 'type' => 'error'])
@if($message)
    <x-ui.alert
        class="mb-5"
        type="{{ $type === 'error' ? 'danger' : 'warning' }}"
        title="{{ $type === 'error' ? 'Ada yang perlu diperbaiki' : 'Perhatian' }}"
    >
        {{ $message }}
    </x-ui.alert>
@endif
