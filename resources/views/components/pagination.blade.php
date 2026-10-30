@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-ink-soft">
            Showing <span class="font-semibold text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> of {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="grid size-9 place-items-center rounded-full text-ink-soft/40 ring-1 ring-ink/5"><x-icon name="chevron-left" class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="grid size-9 place-items-center rounded-full bg-surface ring-1 ring-ink/10 hover:bg-paper-deep" aria-label="Previous page"><x-icon name="chevron-left" class="size-4" /></a>
            @endif

            <span class="px-2 font-semibold">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="grid size-9 place-items-center rounded-full bg-surface ring-1 ring-ink/10 hover:bg-paper-deep" aria-label="Next page"><x-icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="grid size-9 place-items-center rounded-full text-ink-soft/40 ring-1 ring-ink/5"><x-icon name="chevron-right" class="size-4" /></span>
            @endif
        </div>
    </nav>
@endif
