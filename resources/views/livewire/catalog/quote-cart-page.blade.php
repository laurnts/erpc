<div>
    <h1 class="text-2xl font-bold text-black dark:text-white mb-6">Quote Cart</h1>

    @if ($confirmedRequestNumber !== null)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-10 text-center">
            <x-heroicon-o-check-circle class="h-14 w-14 text-green-600 dark:text-green-400"/>
            <p class="text-lg font-semibold text-green-800 dark:text-green-300">Quote request submitted</p>
            <p class="text-sm text-green-700 dark:text-green-400">
                Your request number is <span class="font-mono font-semibold">{{ $confirmedRequestNumber }}</span>.
                Our team will review it and get back to you with a quotation.
            </p>
            <a href="{{ route('catalog.home') }}" class="mt-2 rounded-md bg-primary hover:bg-primary-600 px-4 py-2 text-sm font-medium text-white">
                Continue browsing
            </a>
        </div>
    @elseif ($articles->isEmpty())
        <div class="flex flex-col items-center gap-3 py-16 text-center">
            <x-heroicon-o-shopping-cart class="h-14 w-14 text-gray-300 dark:text-gray-700"/>
            <p class="text-lg font-medium text-gray-700 dark:text-gray-300">Your quote cart is empty</p>
            <a href="{{ route('catalog.home') }}" class="text-sm font-medium text-primary hover:underline">Browse the catalog</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            {{-- Left: cart lines --}}
            <div class="lg:col-span-2 flex flex-col gap-4">
                @error('cart')
                    <div class="rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                        @foreach ($errors->get('cart') as $cartError)
                            <p>{{ $cartError }}</p>
                        @endforeach
                    </div>
                @enderror

                <div class="divide-y divide-gray-100 dark:divide-gray-900 rounded-xl border border-gray-100 dark:border-gray-900">
                    @foreach ($articles as $article)
                        <div wire:key="cart-line-{{ $article->id }}" class="flex items-center gap-4 p-4">
                            <div class="h-16 w-16 shrink-0 rounded-md bg-gray-50 dark:bg-gray-900 overflow-hidden">
                                @if ($article->getFirstMediaUrl('product_images', 'thumb') !== '')
                                    <img src="{{ $article->getFirstMediaUrl('product_images', 'thumb') }}" alt="{{ $article->name }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-700">
                                        <x-heroicon-o-photo class="h-8 w-8"/>
                                    </div>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-black dark:text-white truncate">{{ $article->name }}</p>
                                <div class="text-sm">@include('livewire.catalog.partials.price')</div>
                                @unless (in_array($article->id, $availableIds, true))
                                    <p class="text-xs text-red-600 dark:text-red-400">No longer available — remove this line to submit.</p>
                                @endunless
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="number"
                                       min="0"
                                       step="any"
                                       wire:model="quantities.{{ $article->id }}"
                                       wire:change="updateQuantity({{ $article->id }})"
                                       class="w-24 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-2 py-1.5 text-sm text-gray-900 dark:text-gray-100"
                                       aria-label="Quantity for {{ $article->name }}">
                                <span class="text-xs text-gray-500 dark:text-gray-400 w-10">{{ $article->unit instanceof \App\Enums\Unit ? $article->unit->value : $article->unit }}</span>
                                <button type="button"
                                        wire:click="removeLine({{ $article->id }})"
                                        class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                        aria-label="Remove {{ $article->name }}">
                                    <x-heroicon-o-trash class="h-5 w-5"/>
                                </button>
                            </div>
                        </div>
                        @error('quantities.'.$article->id)
                            <p class="px-4 pb-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>
            </div>

            {{-- Right: sign-in / submit panel --}}
            <div class="lg:sticky lg:top-24 flex flex-col gap-4">
                @if ($isSignedIn)
                    <div class="rounded-xl border border-gray-100 dark:border-gray-900 p-6 flex flex-col gap-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Signed in as <span class="font-medium text-black dark:text-white">{{ $customerName }}</span>
                        </p>
                        <button type="button"
                                wire:click="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="w-full rounded-md bg-primary hover:bg-primary-600 px-5 py-2.5 text-sm font-medium text-white transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit">Request a quote</span>
                            <span wire:loading wire:target="submit">Submitting…</span>
                        </button>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-100 dark:border-gray-900 p-6 flex flex-col gap-4">
                        <p class="font-medium text-black dark:text-white">Sign in to request a quote</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Your cart is saved in this browsing session and will still be here after you sign in.
                        </p>

                        <form wire:submit="signIn" class="flex flex-col gap-3">
                            <input type="email"
                                   wire:model="email"
                                   placeholder="Email address"
                                   autocomplete="email"
                                   class="rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                            @error('email')
                                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <input type="password"
                                   wire:model="password"
                                   placeholder="Password"
                                   autocomplete="current-password"
                                   class="rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                            @error('password')
                                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="signIn"
                                    class="rounded-md bg-primary hover:bg-primary-600 px-4 py-2 text-sm font-medium text-white transition disabled:opacity-60">
                                <span wire:loading.remove wire:target="signIn">Sign in</span>
                                <span wire:loading wire:target="signIn">Signing in…</span>
                            </button>
                        </form>

                        <div class="flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-300 border-t border-gray-100 dark:border-gray-900 pt-4">
                            <span>No account yet?</span>
                            <a href="{{ route('catalog.register') }}" class="font-medium text-primary hover:underline">Register for portal access</a>
                            <a href="{{ url()->getCustomerPortalUrl('login') }}" class="font-medium text-primary hover:underline">Go to the buyer portal</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
