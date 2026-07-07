@php
    /** @var \App\Models\SupplierOrder $record */
    $record = $getRecord();
    $record->loadMissing('currency');
    $currency = $record->currency;
    $lines = $record->hierarchicalDisplayLines();
@endphp

@if($lines->isEmpty())
    <div class="text-gray-500">No items</div>
@else
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Item</th>
                    <th class="px-4 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Qty</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Unit Price</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Tax</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Line Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($lines as $line)
                    @php
                        $isChild = $line['is_child'];
                    @endphp
                    <tr @class([
                        'hover:bg-gray-50 dark:hover:bg-gray-900/50',
                        'text-gray-500 dark:text-gray-400' => $isChild,
                        'bg-primary-50/80 dark:bg-primary-950/40 border-l-[3px] border-primary-500 font-semibold text-gray-900 dark:text-gray-100' => ! $isChild,
                    ])>
                        <td class="px-4 py-2 {{ $isChild ? 'pl-8 text-sm' : '' }}">
                            @if($isChild)
                                <span class="text-gray-400 dark:text-gray-500 mr-1">↳</span>
                            @endif
                            {{ $line['label'] }}
                            @if($line['notes'])
                                <div class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $line['notes'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center {{ $isChild ? 'text-sm' : '' }}">
                            {{ number_format($line['quantity'], 0) }}
                            <span class="text-xs">{{ $line['unit_label'] }}</span>
                        </td>
                        <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm' : '' }}">
                            {{ $currency?->formatNumber($line['unit_price_exc_tax']) ?? number_format($line['unit_price_exc_tax'], 2) }}
                        </td>
                        <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm' : '' }}">
                            {{ $currency?->formatNumber($line['line_tax']) ?? number_format($line['line_tax'], 2) }}
                        </td>
                        <td class="px-4 py-2 text-right {{ $isChild ? 'text-sm font-normal' : '' }}">
                            {{ $currency?->formatNumber($line['line_total']) ?? number_format($line['line_total'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
