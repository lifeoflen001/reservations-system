@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="pagination__link pagination__link--disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">‹</span>
            @else
                <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__ellipsis" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination__link pagination__link--active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination__link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">›</a>
            @else
                <span class="pagination__link pagination__link--disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">›</span>
            @endif
        </div>
    </nav>
@endif
