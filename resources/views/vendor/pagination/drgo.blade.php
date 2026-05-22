@if ($paginator->hasPages())
    <nav class="drgo-pager" role="navigation" aria-label="페이지 이동">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="drgo-pager-btn disabled" aria-disabled="true">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="drgo-pager-btn" rel="prev" aria-label="이전 페이지">‹</a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            {{-- "..." separator --}}
            @if (is_string($element))
                <span class="drgo-pager-dots">…</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="drgo-pager-btn active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="drgo-pager-btn">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="drgo-pager-btn" rel="next" aria-label="다음 페이지">›</a>
        @else
            <span class="drgo-pager-btn disabled" aria-disabled="true">›</span>
        @endif

        <span class="drgo-pager-info">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} / {{ $paginator->total() }}</span>
    </nav>
@endif
