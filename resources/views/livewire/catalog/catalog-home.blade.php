<div>
    <div class="flex flex-col gap-6">
        {{-- Category menu --}}
        <nav class="flex items-center gap-2 overflow-x-auto pb-1" aria-label="Categories">
            <button type="button"
                    wire:click="selectCategory(null)"
                    class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition {{ $category === null ? 'bg-black text-white dark:bg-white dark:text-black' : 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800' }}">
                All articles
            </button>
            @foreach ($categories as $tag)
                <button type="button"
                        wire:key="category-{{ $tag->id }}"
                        wire:click="selectCategory({{ $tag->id }})"
                        class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition {{ $category === $tag->id ? 'bg-black text-white dark:bg-white dark:text-black' : 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-800' }}">
                    {{ $tag->name }}
                </button>
            @endforeach
        </nav>

        {{-- Search --}}
        <div class="relative">
            <input type="search"
                   wire:model.live.debounce.400ms="search"
                   placeholder="Search articles by name, SKU, or description…"
                   class="w-full rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-4 py-3 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-primary focus:ring-primary"
                   aria-label="Search articles">
        </div>

        {{-- Grid --}}
        @if ($articles->isEmpty())
            <div class="flex flex-col items-center gap-3 py-16 text-center">
                <p class="text-lg font-medium text-gray-700 dark:text-gray-300">No articles found</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Try a different search term or category.</p>
                @if ($search !== '')
                    <button type="button" wire:click="clearSearch" class="text-sm font-medium text-primary hover:underline">
                        Clear search
                    </button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach ($articles as $article)
                    <div wire:key="article-{{ $article->id }}"
                         class="flex flex-col rounded-xl border border-gray-100 dark:border-gray-900 bg-white dark:bg-gray-950 overflow-hidden hover:shadow-md transition-shadow">
                        <a href="{{ route('catalog.article', $article) }}" class="block aspect-square bg-gray-50 dark:bg-gray-900">
                            @if ($article->getFirstMediaUrl('product_images', 'thumb') !== '')
                                <img src="{{ $article->getFirstMediaUrl('product_images', 'thumb') }}"
                                     alt="{{ $article->name }}"
                                     class="h-full w-full object-cover"
                                     loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-700">
                                    <x-heroicon-o-photo class="h-16 w-16"/>
                                </div>
                            @endif
                        </a>

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('catalog.article', $article) }}"
                                   class="font-medium text-black dark:text-white hover:text-primary line-clamp-2">
                                    {{ $article->name }}
                                </a>
                                @include('livewire.catalog.partials.availability-badge')
                            </div>

                            @if ($article->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($article->tags as $articleTag)
                                        <span wire:key="article-{{ $article->id }}-tag-{{ $articleTag->id }}"
                                              class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                                            {{ $articleTag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-auto flex flex-col gap-2">
                                <div>@include('livewire.catalog.partials.price')</div>

                                <div class="flex items-center gap-2">
                                    <input type="number"
                                           min="0"
                                           step="any"
                                           placeholder="1"
                                           wire:model="quantities.{{ $article->id }}"
                                           class="w-20 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-2 py-1.5 text-sm text-gray-900 dark:text-gray-100"
                                           aria-label="Quantity for {{ $article->name }}">
                                    <button type="button"
                                            wire:click="addToCart({{ $article->id }})"
                                            class="flex-1 rounded-md bg-primary hover:bg-primary-600 px-3 py-1.5 text-sm font-medium text-white transition">
                                        Add to quote
                                    </button>
                                </div>
                                @error('quantities.'.$article->id)
                                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                {{ $articles->links() }}
            </div>
        @endif
    </div>
</div>
