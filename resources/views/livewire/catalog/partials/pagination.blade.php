@if ($paginator->total() > 0)
    <p class="mb-2 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ sprintf('Showing %s-%s of %s %s', number_format((int) $paginator->firstItem()), number_format((int) $paginator->lastItem()), number_format($paginator->total()), Str::plural('product', $paginator->total())) }}
    </p>
@endif

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-md border border-gray-100 dark:border-gray-900 bg-gray-50 dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-400 dark:text-gray-600 cursor-default select-none">
                Previous
            </span>
        @else
            <button type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                Previous
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-sm text-gray-400 dark:text-gray-600 select-none">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page"
                              class="inline-flex min-w-9 items-center justify-center rounded-md bg-gray-900 dark:bg-white px-3 py-1.5 text-sm font-semibold text-white dark:text-gray-900 select-none">
                            {{ $page }}
                        </span>
                    @else
                        <button type="button"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled"
                                class="inline-flex min-w-9 items-center justify-center rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                Next
            </button>
        @else
            <span class="inline-flex items-center rounded-md border border-gray-100 dark:border-gray-900 bg-gray-50 dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-400 dark:text-gray-600 cursor-default select-none">
                Next
            </span>
        @endif
    </nav>
@endif
