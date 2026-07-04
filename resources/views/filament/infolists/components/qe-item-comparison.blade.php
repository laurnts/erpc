@php
    $data = $getState();
    $items = $data['items'] ?? [];
    $suppliers = collect($data['suppliers'] ?? []);
    $isMixedRequest = $data['request']['is_mixed'] ?? false;

    if (empty($items) || $suppliers->isEmpty()) {
        return;
    }
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Item</th>
                <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Qty</th>
                @foreach($suppliers as $supplier)
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">
                        {{ $supplier['name'] ?? 'Unknown' }}
                        <span class="text-xs text-gray-400">({{ $supplier['currency_code'] ?? 'USD' }})</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                        {{ $item['description'] ?? 'Unknown Item' }}
                    </td>
                    <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">
                        {{ $item['quantity'] ?? 0 }} {{ $item['unit'] ?? '' }}
                    </td>
                    @foreach($suppliers as $supplier)
                        @php
                            $supplierId = (string) $supplier['id'];
                            $priceData = $item['prices'][$supplierId] ?? null;
                            $isBestPrice = $priceData['is_best_price'] ?? false;
                            $isSelected = $priceData['is_selected'] ?? false;
                        @endphp
                        <td class="px-3 py-2 text-right {{ $isSelected ? 'bg-primary-50 dark:bg-primary-950 ring-2 ring-inset ring-primary-500' : ($isBestPrice ? 'bg-success-50 dark:bg-success-950' : '') }}">
                            @if($priceData)
                                <div class="{{ $isSelected ? 'font-semibold text-primary-600 dark:text-primary-400' : ($isBestPrice ? 'font-semibold text-success-600 dark:text-success-400' : 'text-gray-900 dark:text-gray-100') }}">
                                    @if($isSelected)
                                        <x-heroicon-s-check-circle class="inline-block w-4 h-4 mr-1 text-primary-500" />
                                    @endif
                                    @if($isBestPrice)
                                        <x-heroicon-s-star class="inline-block w-4 h-4 mr-1 text-success-500" />
                                    @endif
                                    {{ number_format($priceData['unit_price'] ?? 0, 2) }}/{{ $item['unit'] ?? 'ea' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Item Total: {{ number_format($priceData['line_subtotal'] ?? 0, 2) }}
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
            {{-- Subtotal row --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300" colspan="2">Subtotal</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">
                        {{ number_format($supplier['subtotal'] ?? 0, 2) }}
                    </td>
                @endforeach
            </tr>
            {{-- Tax row --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300" colspan="2">Tax</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                        {{ number_format($supplier['tax_total'] ?? 0, 2) }}
                    </td>
                @endforeach
            </tr>
            {{-- Goods Total row (mixed requests only: the ranking basis, excludes services pricing) --}}
            @if($isMixedRequest)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300" colspan="2">Goods Total</td>
                    @foreach($suppliers as $supplier)
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($supplier['goods_total'] ?? 0, 2) }}
                        </td>
                    @endforeach
                </tr>
            @endif
            {{-- Grand Total row --}}
            <tr class="bg-gray-50 dark:bg-gray-900">
                <td class="px-3 py-2 font-bold text-gray-900 dark:text-gray-100" colspan="2">Grand Total</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-right font-bold text-gray-900 dark:text-gray-100">
                        {{ number_format($supplier['grand_total'] ?? 0, 2) }}
                    </td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>
