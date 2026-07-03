@php
    /** @var \App\Models\ProfitAndLoss $record */
    $record = $getRecord();
    
    // Use the buyer quote this PNL was created from (includes soft-deleted quotes)
    $buyerQuote = $record->resolveSourceBuyerQuote();
    $buyerQuote?->loadMissing('currency');
    
    if ($buyerQuote === null) {
        return;
    }
    
    // Get buyer quote items with supplier info, grouped by supplier (exclude invalid orphans)
    $items = $buyerQuote->items()
        ->whereNotNull('request_item_id')
        ->with(['supplierQuoteItem.supplierQuote.supplier', 'supplierQuoteItem.supplierQuote.currency', 'article', 'requestItem'])
        ->orderBy('sort_order')
        ->get();
    
    // Group items by supplier (using supplier_quote_item relationship)
    $groupedItems = $items->groupBy(function ($item) {
        return $item->supplierQuoteItem?->supplierQuote?->supplier_id ?? 0;
    });
    
    if ($groupedItems->isEmpty()) {
        return;
    }
    
    $buyerCurrency = $buyerQuote->currency;
    $isServiceRequest = $record->request?->isServiceRequest() ?? false;
@endphp

<div class="space-y-6">
    @foreach($groupedItems as $supplierId => $supplierItems)
        @php
            $firstItem = $supplierItems->first();
            $supplier = $firstItem->supplierQuoteItem?->supplierQuote?->supplier;
            $supplierCurrency = $firstItem->supplierQuoteItem?->supplierQuote?->currency;
            $supplierName = $supplier?->name ?? 'No Supplier';
            $groupTotals = \App\Models\BuyerQuoteItem::collectTotals($supplierItems, $isServiceRequest);
            $supplierCostTotal = $groupTotals->costTotal;
            $supplierNetSell = $groupTotals->subtotal;      // net revenue (margin base)
            $supplierMargin = $groupTotals->marginAmount;   // net sell - cost (VAT excluded)
            $supplierGrossTotal = $groupTotals->grandTotal; // gross, for the Line Total footer
            $organizedItems = \App\Models\BuyerQuoteItem::organizeHierarchically($supplierItems);
        @endphp
        
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Supplier Header --}}
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-building-storefront class="w-5 h-5 text-gray-500" />
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                            {{ $supplierName }}
                        </span>
                        @if($supplierCurrency)
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                (Cost: {{ $supplierCurrency->code }})
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Cost:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $buyerCurrency?->formatNumber($supplierCostTotal) ?? number_format($supplierCostTotal, 2) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">Sell:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $buyerCurrency?->formatNumber($supplierNetSell) ?? number_format($supplierNetSell, 2) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500">Margin:</span>
                            <span class="font-medium {{ $supplierMargin >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $buyerCurrency?->formatNumber($supplierMargin) ?? number_format($supplierMargin, 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Items Table --}}
            <table class="w-full text-sm">
                <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Item</th>
                        <th class="px-4 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Qty</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Cost</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Sell</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Tax</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Margin</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($organizedItems as $entry)
                        @php
                            /** @var \App\Models\BuyerQuoteItem $item */
                            $item = $entry['item'];
                            $isChild = $entry['is_child'];
                            $lineTax = $item->getEffectiveLineTax();
                            $lineTotal = $item->getEffectiveLineTotal();
                            $marginPercent = $item->getDisplayMarginPercent();
                            $itemMargin = ((float) $item->unit_price_exc_tax - (float) $item->cost_price) * (float) $item->quantity;
                        @endphp
                        <tr class="{{ $isChild
                            ? 'text-gray-500 dark:text-gray-400'
                            : 'bg-primary-50/80 dark:bg-primary-950/40 border-l-[3px] border-primary-500 font-semibold text-gray-900 dark:text-gray-100'
                        }} hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <td class="px-4 py-2 {{ $isChild ? 'pl-8 text-sm' : '' }}">
                                @if($isChild)
                                    <span class="text-gray-400 dark:text-gray-500 mr-1">↳</span>
                                @endif
                                @if($item->article)
                                    @if($isChild)
                                        <span class="text-xs text-gray-400 dark:text-gray-500">[{{ $item->article->code }}]</span>
                                        {{ $item->article->name }}
                                    @else
                                        <span class="text-xs text-primary-600 dark:text-primary-400">[{{ $item->article->code }}]</span>
                                        {{ $item->article->name }}
                                    @endif
                                @else
                                    {{ $item->description }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center {{ $isChild ? 'text-sm' : '' }}">
                                {{ number_format((float) $item->quantity, 0) }}
                                <span class="text-xs">{{ $item->unit }}</span>
                            </td>
                            <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm' : '' }}">
                                {{ $buyerCurrency?->formatNumber((float) $item->cost_price) ?? number_format((float) $item->cost_price, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm font-normal' : '' }}">
                                {{ $buyerCurrency?->formatNumber((float) $item->unit_price_exc_tax) ?? number_format((float) $item->unit_price_exc_tax, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm' : '' }}">
                                {{ $buyerCurrency?->formatNumber($lineTax) ?? number_format($lineTax, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm' : '' }} {{ ! $isChild && $itemMargin >= 0 ? 'text-success-600 dark:text-success-400' : '' }} {{ ! $isChild && $itemMargin < 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">
                                {{ number_format($marginPercent, 0) }}%
                            </td>
                            <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm font-normal' : 'font-bold text-primary-700 dark:text-primary-300' }}">
                                {{ $buyerCurrency?->formatNumber($lineTotal) ?? number_format($lineTotal, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-primary-100 dark:bg-primary-950/50 border-t-2 border-primary-400 dark:border-primary-600">
                        <td colspan="6" class="px-4 py-2.5 text-right font-semibold text-primary-800 dark:text-primary-200">
                            Supplier Subtotal
                            @if($isServiceRequest)
                                <span class="block text-xs font-normal text-primary-600/80 dark:text-primary-400/80">(main items)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-base font-bold text-primary-700 dark:text-primary-300">
                            {{ $buyerCurrency?->formatNumber($supplierGrossTotal) ?? number_format($supplierGrossTotal, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
    
</div>
