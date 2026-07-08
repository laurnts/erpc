<div class="space-y-4">
    @if($this->hasQuotes)
        {{-- Header with Quick Actions --}}
        <div class="flex items-center justify-between gap-4 py-2">
            <div class="flex items-center gap-4 text-sm">
                @if($this->selectedSuppliersCount > 0)
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Selected:</span>
                        <span class="font-semibold ml-1">{{ count(array_filter($this->itemSelections)) }}/{{ $this->requestItems->count() }} items</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Suppliers:</span>
                        <span class="font-semibold ml-1">{{ $this->selectedSuppliersCount }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Total:</span>
                        <span class="font-semibold ml-1 text-primary-600 dark:text-primary-400">{{ $this->formatCurrency($this->selectionTotal) }}</span>
                    </div>
                @else
                    @if($this->hasPricesEntered)
                        <span class="text-gray-500 dark:text-gray-400">Click on a price to select supplier for each item</span>
                    @endif    
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($this->outcomesAnnounced)
                    <x-filament::badge color="warning" icon="heroicon-o-lock-closed">
                        Outcomes announced — selections locked
                    </x-filament::badge>
                @endif

                @if($this->hasPricesEntered && ! $this->outcomesAnnounced)
                    <x-filament::button
                        size="sm"
                        color="gray"
                        wire:click="selectBestPrices"
                        icon="heroicon-o-sparkles"
                    >
                        Select Best Prices
                    </x-filament::button>
                @endif

                @if($this->selectedSuppliersCount > 0 && ! $this->outcomesAnnounced)
                    <x-filament::button
                        size="sm"
                        color="gray"
                        wire:click="clearSelections"
                        icon="heroicon-o-x-mark"
                    >
                        Clear
                    </x-filament::button>

                    <x-filament::button
                        size="sm"
                        color="primary"
                        wire:click="applySelections"
                        icon="heroicon-o-check-circle"
                    >
                        Apply
                    </x-filament::button>
                @endif

                @if($this->hasAppliedSelections && ! $this->outcomesAnnounced)
                    <x-filament::button
                        size="sm"
                        color="warning"
                        wire:click="announceOutcomes"
                        wire:confirm="Announce outcomes to suppliers? Losing quotes will be marked as rejected, suppliers will be notified of their result, and selections will be locked for this request. This cannot be undone."
                        icon="heroicon-o-megaphone"
                    >
                        Announce Outcomes
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- Comparison Matrix --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[250px]">
                            Item
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[80px]">
                            Qty
                        </th>
                        @foreach($this->quotes as $quote)
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[180px]">
                                <div class="flex flex-col gap-1">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $quote->supplier->name }}
                                        </span>
                                        <div class="flex items-center gap-1">
                                            @if($quote->supplier->is_taxable)
                                                <x-filament::badge size="xs" color="info">
                                                    Tax
                                                </x-filament::badge>
                                            @else
                                                <x-filament::badge size="xs" color="gray">
                                                    No Tax
                                                </x-filament::badge>
                                            @endif
                                            @if($this->hasPricesEntered)
                                                @if($quote->getKey() === $this->bestOverallQuoteId)
                                                    <x-filament::badge size="xs" color="success">
                                                        Best
                                                    </x-filament::badge>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs font-normal normal-case text-gray-500">
                                        <span>{{ $quote->currency->code }}</span>
                                        <span class="text-gray-300">|</span>
                                        <span>{{ $quote->formatted_total }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="selectSingleSupplier({{ $quote->getKey() }})"
                                        class="text-xs text-primary-600 hover:text-primary-500 dark:text-primary-400 font-normal normal-case text-left"
                                    >
                                        Select all
                                    </button>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($this->requestItems as $requestItem)
                        @php
                            $bestQuoteId = $this->bestPricesByItem[$requestItem->getKey()] ?? null;
                            $selectedQuoteId = $this->itemSelections[$requestItem->getKey()] ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            {{-- Item Description --}}
                            <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-4 py-3 text-sm">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $requestItem->display_text }}
                                </div>
                                @if($requestItem->notes)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ Str::limit($requestItem->notes, 50) }}
                                    </div>
                                @endif
                            </td>

                            {{-- Quantity --}}
                            <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-300">
                                {{ number_format((float) $requestItem->quantity, 0) }}
                                <span class="text-xs text-gray-400">{{ $requestItem->unit }}</span>
                            </td>

                            {{-- Price Cells per Supplier --}}
                            @foreach($this->quotes as $quote)
                                @php
                                    $quoteItem = $this->priceMatrix[$requestItem->getKey()][$quote->getKey()] ?? null;
                                    $isBestPrice = $bestQuoteId === $quote->getKey();
                                    $isSelected = $selectedQuoteId === $quote->getKey();
                                @endphp
                                <td class="px-4 py-3 text-sm {{ $isBestPrice ? 'bg-success-50 dark:bg-success-900/20' : '' }}">
                                    @if($quoteItem)
                                        <button
                                            type="button"
                                            wire:click="selectSupplierForItem({{ $requestItem->getKey() }}, {{ $quote->getKey() }})"
                                            class="w-full text-left p-2 -m-2 rounded-lg transition-all {{ $isSelected ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                                        >
                                            <div class="flex items-center justify-between gap-2">
                                                <div>
                                                    <div class="font-medium {{ $isBestPrice ? 'text-success-700 dark:text-success-400' : 'text-gray-900 dark:text-gray-100' }}">
                                                        {{ $quote->currency?->format((float) $quoteItem->unit_price_exc_tax) ?? number_format((float) $quoteItem->unit_price_exc_tax, 2) }}
                                                        <span class="text-xs text-gray-400 font-normal">/{{ $quoteItem->unit }}</span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Total: {{ $quote->currency?->format((float) $quoteItem->line_total) ?? number_format((float) $quoteItem->line_total, 2) }}
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    @if($isBestPrice)
                                                        <x-heroicon-s-star class="w-4 h-4 text-success-500" />
                                                    @endif
                                                    @if($isSelected)
                                                        <x-heroicon-s-check-circle class="w-5 h-5 text-primary-500" />
                                                    @endif
                                                </div>
                                            </div>
                                        </button>
                                    @else
                                        <div class="text-gray-400 dark:text-gray-600 text-center italic text-xs">
                                            Not quoted
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="2" class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Quote Total
                        </td>
                        @foreach($this->quotes as $quote)
                            <td class="px-4 py-3 text-sm font-semibold {{ $this->hasPricesEntered ? ($quote->getKey() === $this->bestOverallQuoteId ? 'text-success-700 dark:text-success-400' : 'text-gray-900 dark:text-gray-100') : '' }}">
                                {{ $quote->formatted_total }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-6 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-1">
                <x-heroicon-s-star class="w-4 h-4 text-success-500" />
                <span>Best price</span>
            </div>
            <div class="flex items-center gap-1">
                <x-heroicon-s-check-circle class="w-4 h-4 text-primary-500" />
                <span>Selected</span>
            </div>
            <div class="flex items-center gap-1">
                <x-filament::badge size="xs" color="info">Tax</x-filament::badge>
                <span>Taxable company</span>
            </div>
            <div class="flex items-center gap-1">
                <x-filament::badge size="xs" color="gray">No Tax</x-filament::badge>
                <span>Non-taxable company</span>
            </div>
        </div>

    @else
        {{-- No Quotes State --}}
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">No quotes to compare</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    There are no received or selected supplier quotes for this request.
                </p>
            </div>
        </x-filament::section>
    @endif
</div>
