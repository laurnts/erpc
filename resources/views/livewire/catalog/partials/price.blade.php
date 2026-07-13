@if ($article->show_price && $article->list_price !== null)
    <span class="font-semibold text-black dark:text-white">
        {{ $baseCurrency !== null ? $baseCurrency->format((float) $article->list_price) : number_format((float) $article->list_price, 2) }}
    </span>
@else
    <span class="text-[0.6em] font-medium text-gray-500 dark:text-gray-400">Price on request</span>
@endif
