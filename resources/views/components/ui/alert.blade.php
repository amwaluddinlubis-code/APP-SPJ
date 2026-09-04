@props(['type' => 'info', 'title' => null, 'icon' => null])
@php
    $type = in_array($type, ['info','success','warning','danger'], true) ? $type : 'info';
    $defaultIcon = ['info' => 'i', 'success' => '✓', 'warning' => '!', 'danger' => '!'][$type];
@endphp
<div {{ $attributes->class(['ui-alert', 'ui-alert-'.$type]) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}">
    <span class="ui-alert-icon" aria-hidden="true">{{ $icon ?? $defaultIcon }}</span>
    <div class="min-w-0 flex-1">
        @if($title)<div class="ui-alert-title">{{ $title }}</div>@endif
        <div class="ui-alert-body">{{ $slot }}</div>
    </div>
</div>
