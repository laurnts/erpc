<a href="{{ route('catalog.cart') }}"
   class="relative inline-flex items-center p-2 text-gray-700 dark:text-gray-200 hover:text-primary"
   aria-label="Quote cart">
    <x-heroicon-o-shopping-cart class="h-6 w-6"/>
    @if ($count > 0)
        <span class="absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-white">
            {{ $count }}
        </span>
    @endif
</a>
