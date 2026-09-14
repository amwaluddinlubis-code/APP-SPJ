@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="ui-pagination" data-pagination-standard="segmented">
        <span class="ui-pagination-summary">
            Menampilkan <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> baris
        </span>

        <span class="ui-pagination-group" aria-label="Navigasi halaman">
            @if ($paginator->onFirstPage())
                <span class="ui-pagination-control is-disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="ui-pagination-control" aria-label="{{ __('pagination.previous') }}">{{ __('pagination.previous') }}</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="ui-pagination-control ui-pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="ui-pagination-control is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="ui-pagination-control" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="ui-pagination-control" aria-label="{{ __('pagination.next') }}">{{ __('pagination.next') }}</button>
            @else
                <span class="ui-pagination-control is-disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </span>
    </nav>
@endif
