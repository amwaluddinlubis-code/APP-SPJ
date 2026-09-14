@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-2">
        <div class="flex flex-1 items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-9 flex-1 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 text-sm font-bold opacity-40" style="color: var(--ui-fg-muted)">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 text-sm font-bold transition hover:border-[var(--theme-accent)]" style="color: var(--ui-fg)">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 text-sm font-bold transition hover:border-[var(--theme-accent)]" style="color: var(--ui-fg)">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex h-9 flex-1 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 text-sm font-bold opacity-40" style="color: var(--ui-fg-muted)">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden flex-1 items-center justify-end sm:flex">
            <div>
                <span class="inline-flex items-center gap-1">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="inline-flex h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 opacity-40" aria-hidden="true" style="color: var(--ui-fg-muted)">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 transition hover:border-[var(--theme-accent)]" style="color: var(--ui-fg-muted)" aria-label="{{ __('pagination.previous') }}">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="inline-flex h-9 min-w-9 cursor-default items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 text-sm font-bold" style="color: var(--ui-fg-muted)">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-bold shadow-sm" style="background: var(--theme-action-bg); color: var(--theme-action-fg);">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 text-sm font-bold transition hover:border-[var(--theme-accent)]" style="color: var(--ui-fg-muted)" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 transition hover:border-[var(--theme-accent)]" style="color: var(--ui-fg-muted)" aria-label="{{ __('pagination.next') }}">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="inline-flex h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-2 opacity-40" aria-hidden="true" style="color: var(--ui-fg-muted)">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
