@if ($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $visiblePages = collect(array_merge(
            range(1, min(3, $lastPage)),
            range(max(1, $lastPage - 2), $lastPage),
        ))->unique()->sort()->values();
    @endphp

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="ui-pagination" data-pagination-standard="segmented">
        <span class="ui-pagination-summary">
            Menampilkan <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> baris
        </span>

        <span class="ui-pagination-group" aria-label="Navigasi halaman">
            @if ($paginator->onFirstPage())
                <span class="ui-pagination-control is-disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ui-pagination-control" aria-label="{{ __('pagination.previous') }}">{{ __('pagination.previous') }}</a>
            @endif

            @php $previousPage = null; @endphp
            @foreach ($visiblePages as $page)
                @if ($previousPage !== null && $page > $previousPage + 1)
                    <span class="ui-pagination-control ui-pagination-ellipsis" aria-hidden="true">…</span>
                @endif

                @if ($page === $paginator->currentPage())
                    <span class="ui-pagination-control is-active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="ui-pagination-control" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
                @php $previousPage = $page; @endphp
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ui-pagination-control" aria-label="{{ __('pagination.next') }}">{{ __('pagination.next') }}</a>
            @else
                <span class="ui-pagination-control is-disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </span>
    </nav>
@endif
