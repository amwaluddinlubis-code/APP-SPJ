@props([
    'pagination' => 'auto',
    'minWidth' => null,
    'compact' => false,
])

<div data-table-container class="ui-table-container">
    <table
        {{ $attributes->class([
            'app-data-table ui-table w-full',
            'ui-table-compact' => $compact,
        ]) }}
        data-pagination="{{ $pagination }}"
        @if($minWidth) style="min-width: {{ $minWidth }}" @endif
    >
        {{ $slot }}
    </table>
</div>
