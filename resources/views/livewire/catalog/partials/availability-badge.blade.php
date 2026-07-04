@if ($article->in_stock)
    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/40 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-300">
        In stock
    </span>
@elseif ($article->has_quantity_data)
    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/40 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:text-red-300">
        Out of stock
    </span>
@else
    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
        On request
    </span>
@endif
