@php
    /** @var \App\Models\ProfitAndLoss $record */
    $record = $getRecord();
    
    // Always use the latest valid buyer quote (not REJECTED, EXPIRED, or SUPERSEDED)
    // This ensures PNL always reflects the most recent quote changes
    $buyerQuote = null;
    if ($record->request !== null) {
        $buyerQuote = $record->request->buyerQuotes()
            ->whereNotIn('status', [
                \App\Enums\BuyerQuoteStatus::REJECTED,
                \App\Enums\BuyerQuoteStatus::EXPIRED,
                \App\Enums\BuyerQuoteStatus::SUPERSEDED
            ])
            ->latest()
            ->first();
    }
    
    if ($buyerQuote === null) {
        return;
    }
    
    // Get buyer quote items with supplier info, grouped by supplier
    $items = $buyerQuote->items()
        ->with(['supplierQuoteItem.supplierQuote.supplier', 'supplierQuoteItem.supplierQuote.currency', 'article'])
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
@endphp

<div class="space-y-6">
    @foreach($groupedItems as $supplierId => $supplierItems)
        @php
            $firstItem = $supplierItems->first();
            $supplier = $firstItem->supplierQuoteItem?->supplierQuote?->supplier;
            $supplierCurrency = $firstItem->supplierQuoteItem?->supplierQuote?->currency;
            $supplierName = $supplier?->name ?? 'No Supplier';
            $supplierTotal = $supplierItems->sum(fn ($item) => (float) $item->line_total);
            $supplierCostTotal = $supplierItems->sum(fn ($item) => (float) $item->cost_price * (float) $item->quantity);
            $supplierMargin = $supplierTotal - $supplierCostTotal;
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
                                {{ $buyerCurrency?->formatNumber($supplierTotal) ?? number_format($supplierTotal, 2) }}
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
                    @foreach($supplierItems as $item)
                        @php
                            $itemMargin = ((float) $item->unit_price_exc_tax - (float) $item->cost_price) * (float) $item->quantity;
                            $marginPercent = (float) $item->cost_price > 0 
                                ? (((float) $item->unit_price_exc_tax - (float) $item->cost_price) / (float) $item->cost_price) * 100 
                                : 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <td class="px-4 py-2 text-gray-900 dark:text-gray-100">
                                @if($item->article)
                                    <span class="text-xs text-gray-500">[{{ $item->article->code }}]</span>
                                    {{ $item->article->name }}
                                @else
                                    {{ $item->description }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center text-gray-600 dark:text-gray-400">
                                {{ number_format((float) $item->quantity, 0) }}
                                <span class="text-xs">{{ $item->unit }}</span>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">
                                {{ $buyerCurrency?->formatNumber((float) $item->cost_price) ?? number_format((float) $item->cost_price, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">
                                {{ $buyerCurrency?->formatNumber((float) $item->unit_price_exc_tax) ?? number_format((float) $item->unit_price_exc_tax, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">
                                {{ $buyerCurrency?->formatNumber((float) $item->line_tax) ?? number_format((float) $item->line_tax, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right {{ $itemMargin >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ number_format($marginPercent, 1) }}%
                            </td>
                            <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-gray-100">
                                {{ $buyerCurrency?->formatNumber((float) $item->line_total) ?? number_format((float) $item->line_total, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="6" class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">
                            Supplier Subtotal:
                        </td>
                        <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-gray-100">
                            {{ $buyerCurrency?->formatNumber($supplierTotal) ?? number_format($supplierTotal, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
    
</div>
