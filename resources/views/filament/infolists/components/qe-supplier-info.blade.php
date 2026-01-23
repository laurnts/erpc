@php
    /** @var \App\Models\QuotationEvaluation $record */
    $record = $getRecord();
    
    // Get supplier quote IDs from snapshot data
    $snapshotData = $record->data;
    $snapshotSuppliers = collect($snapshotData['suppliers'] ?? []);
    
    if ($snapshotSuppliers->isEmpty()) {
        return;
    }
    
    // Get supplier quote IDs from snapshot
    $quoteIds = $snapshotSuppliers->pluck('id')->filter()->toArray();
    
    // Load actual supplier quotes with supplier relationships
    $quotes = \App\Models\SupplierQuote::query()
        ->whereIn('id', $quoteIds)
        ->with(['supplier', 'currency'])
        ->get()
        ->keyBy('id');
    
    // Build suppliers array with live data
    $suppliers = [];
    foreach ($snapshotSuppliers as $snapshotSupplier) {
        $quoteId = $snapshotSupplier['id'] ?? null;
        $quote = $quotes->get($quoteId);
        
        if ($quote && $quote->supplier) {
            $suppliers[] = [
                'id' => $quote->getKey(),
                'name' => $quote->supplier->name ?? 'Unknown',
                'currency_code' => $quote->currency?->code ?? 'USD',
                'delivery_type' => $quote->supplier->delivery_type ?? null,
                'delivery_type_details' => $quote->supplier->delivery_type_details ?? null,
                'is_taxable' => $quote->supplier->is_taxable ?? false,
                'delivery_term' => $quote->supplier->delivery_term ?? null,
                'payment_terms_days' => $quote->supplier->payment_terms_days ?? null,
            ];
        } else {
            // Fallback to snapshot data if quote not found
            $suppliers[] = $snapshotSupplier;
        }
    }
    
    $suppliers = collect($suppliers);
    
    if ($suppliers->isEmpty()) {
        return;
    }
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Attribute</th>
                @foreach($suppliers as $supplier)
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">
                        {{ $supplier['name'] ?? 'Unknown' }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{-- Delivery Type --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Delivery Type</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                        {{ $supplier['delivery_type'] ?? '—' }}
                        @if(!empty($supplier['delivery_type_details']))
                            <span class="text-xs text-gray-400">({{ $supplier['delivery_type_details'] }})</span>
                        @endif
                    </td>
                @endforeach
            </tr>
            {{-- Taxable --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Taxable</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2">
                        @if($supplier['is_taxable'] ?? false)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">
                                Yes
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                No
                            </span>
                        @endif
                    </td>
                @endforeach
            </tr>
            {{-- Delivery Term --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Delivery Term</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                        {{ $supplier['delivery_term'] ?? '—' }}
                    </td>
                @endforeach
            </tr>
            {{-- Payment Terms --}}
            <tr class="border-b border-gray-100 dark:border-gray-800">
                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">Payment Terms</td>
                @foreach($suppliers as $supplier)
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                        @if(isset($supplier['payment_terms_days']))
                            Net {{ $supplier['payment_terms_days'] }} days
                        @else
                            —
                        @endif
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
