@props(['label'])
<div {{ $attributes->class(['ui-detail-item']) }}>
    <dt class="ui-detail-label">{{ $label }}</dt>
    <dd class="ui-detail-value">{{ $slot }}</dd>
</div>
