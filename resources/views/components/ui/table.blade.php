@props([
    'pagination' => 'auto',
    'minWidth' => null,
    'compact' => false,
])

<div data-table-container class="overflow-x-auto">
    <table
        {{ $attributes->class([
            'app-data-table w-full',
            'text-xs' => $compact,
        ]) }}
        data-pagination="{{ $pagination }}"
        @if($minWidth) style="min-width: {{ $minWidth }}" @endif
    >
        {{ $slot }}
    </table>
</div>
