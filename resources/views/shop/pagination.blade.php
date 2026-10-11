@if ($paginator->hasPages())
    <nav aria-label="Pages" class="mt-14 flex items-center justify-between gap-4 border-t border-rule pt-6">
        @if ($paginator->onFirstPage())
            <span class="text-ink-soft" aria-disabled="true">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="link font-semibold">Previous</a>
        @endif

        <p class="text-sm text-ink-soft">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="link font-semibold">Next</a>
        @else
            <span class="text-ink-soft" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
