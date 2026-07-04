<div>
    <a href="{{ route('catalog.home') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-primary mb-6">
        <x-heroicon-o-arrow-left class="h-4 w-4"/>
        Back to catalog
    </a>

    @php
        $mediaItems = $article->getMedia('product_images');
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        {{-- Gallery --}}
        <div x-data="{ active: 0 }" class="flex flex-col gap-3">
            <div class="aspect-square rounded-xl bg-gray-50 dark:bg-gray-900 overflow-hidden">
                @if ($mediaItems->isEmpty())
                    <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-700">
                        <x-heroicon-o-photo class="h-24 w-24"/>
                    </div>
                @else
                    @foreach ($mediaItems as $media)
                        <img x-show="active === {{ $loop->index }}"
                             src="{{ $media->getUrl('medium') }}"
                             alt="{{ $article->name }}"
                             class="h-full w-full object-contain">
                    @endforeach
                @endif
            </div>

            @if ($mediaItems->count() > 1)
                <div class="flex gap-2 overflow-x-auto">
                    @foreach ($mediaItems as $media)
                        <button type="button"
                                x-on:click="active = {{ $loop->index }}"
                                :class="active === {{ $loop->index }} ? 'ring-2 ring-primary' : 'ring-1 ring-gray-200 dark:ring-gray-800'"
                                class="h-16 w-16 shrink-0 rounded-md overflow-hidden bg-gray-50 dark:bg-gray-900">
                            <img src="{{ $media->getUrl('thumb') }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <h1 class="text-2xl font-bold text-black dark:text-white">{{ $article->name }}</h1>
                @include('livewire.catalog.partials.availability-badge')
            </div>

            @if ($article->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($article->tags as $articleTag)
                        <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                            {{ $articleTag->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="text-xl">@include('livewire.catalog.partials.price')</div>

            @if ($article->description !== null && $article->description !== '')
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">{{ $article->description }}</p>
            @endif

            <dl class="divide-y divide-gray-100 dark:divide-gray-900 border-y border-gray-100 dark:border-gray-900 text-sm">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500 dark:text-gray-400">Unit</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $article->unit instanceof \App\Enums\Unit ? $article->unit->value : $article->unit }}</dd>
                </div>
                @if (is_array($article->attributes) && $article->attributes !== [])
                    @foreach ($article->attributes as $attributeName => $attributeValue)
                        <div class="flex justify-between py-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ is_string($attributeName) ? str($attributeName)->headline() : $attributeName }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ is_scalar($attributeValue) ? $attributeValue : json_encode($attributeValue) }}</dd>
                        </div>
                    @endforeach
                @endif
            </dl>

            <div class="flex items-center gap-3 mt-2">
                <input type="number"
                       min="0"
                       step="any"
                       wire:model="quantity"
                       class="w-24 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100"
                       aria-label="Quantity">
                <button type="button"
                        wire:click="addToCart"
                        class="rounded-md bg-primary hover:bg-primary-600 px-5 py-2 text-sm font-medium text-white transition">
                    Add to quote
                </button>
            </div>
            @error('quantity')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
