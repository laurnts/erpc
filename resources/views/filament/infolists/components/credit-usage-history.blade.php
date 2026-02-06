@php
    /** @var \App\Models\Company $record */
    $record = $getRecord();
    $history = $record->creditUsageHistory()->orderBy('created_at', 'desc')->get();
    $currencyCode = \Filament\Facades\Filament::getTenant() instanceof \App\Models\Team 
        ? \Filament\Facades\Filament::getTenant()->getBaseCurrencyCode() 
        : 'USD';
@endphp

@if($history->isEmpty())
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No credit usage history found.
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Date</th>
                    <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Type</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Amount</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Available Credit Before</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Available Credit After</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Credit Used Before</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Credit Used After</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Description</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Related Order</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Created By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $item)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $item->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $typeColor = $item->transaction_type === 'used' ? 'danger' : 'success';
                                $typeLabel = $item->transaction_type === 'used' ? 'Used' : 'Restored';
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-{{ $typeColor }}-100 text-{{ $typeColor }}-800 dark:bg-{{ $typeColor }}-900 dark:text-{{ $typeColor }}-200">
                                {{ $typeLabel }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $item->amount, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $item->available_credit_before, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $item->available_credit_after, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $item->credit_used_before, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $item->credit_used_after, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $item->description ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            @if($item->related)
                                @php
                                    $related = $item->related;
                                    if ($related instanceof \App\Models\BuyerOrder) {
                                        $url = \App\Filament\Resources\RequestResource::getUrl('view', ['record' => $related->request_id]);
                                        $label = $related->order_number ?? 'Order #' . $related->id;
                                    } else {
                                        $url = null;
                                        $label = class_basename($related::class) . ' #' . $related->id;
                                    }
                                @endphp
                                @if($url)
                                    <a href="{{ $url }}" class="text-primary-600 dark:text-primary-400 hover:underline">
                                        {{ $label }}
                                    </a>
                                @else
                                    {{ $label }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $item->createdBy->name ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
