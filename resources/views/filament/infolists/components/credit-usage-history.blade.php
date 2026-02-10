@php
    /** @var \App\Models\Company $record */
    $record = $getRecord();
    $history = $record->creditUsageHistory()->orderBy('created_at', 'desc')->get();
    $currencyCode = \Filament\Facades\Filament::getTenant() instanceof \App\Models\Team 
        ? \Filament\Facades\Filament::getTenant()->getBaseCurrencyCode() 
        : 'USD';
@endphp

{{-- Ensure Tailwind compiles badge classes --}}
<div class="hidden bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200 bg-yellow-100 text-yellow-900 dark:bg-yellow-400 dark:text-yellow-900 bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200"></div>

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
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">Amount</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Max Credit Limit Before</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Max Credit Limit After</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Available Credit Before</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Available Credit After</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Description</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Related Entity</th>
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
                                // Handle 'approved' transaction type - determine if it's increase or decrease
                                if ($item->transaction_type === 'approved') {
                                    $maxBefore = (float) $item->max_credit_limit_before;
                                    $maxAfter = (float) $item->max_credit_limit_after;
                                    if ($maxAfter > $maxBefore) {
                                        $typeLabel = 'Limit Increase';
                                        $typeColor = 'success';
                                    } elseif ($maxAfter < $maxBefore) {
                                        $typeLabel = 'Limit Decrease';
                                        $typeColor = 'warning';
                                    } else {
                                        $typeLabel = 'Approved';
                                        $typeColor = 'success';
                                    }
                                } else {
                                    $typeMap = [
                                        'limit_increase' => ['label' => 'Limit Increase', 'color' => 'success'],
                                        'limit_decrease' => ['label' => 'Limit Decrease', 'color' => 'warning'],
                                        'credit' => ['label' => 'Credit', 'color' => 'success'],
                                        'debit' => ['label' => 'Debit', 'color' => 'warning'],
                                        'used' => ['label' => 'Used', 'color' => 'danger'], // Legacy
                                        'restored' => ['label' => 'Restored', 'color' => 'success'], // Legacy
                                    ];
                                    $typeInfo = $typeMap[$item->transaction_type] ?? ['label' => ucfirst($item->transaction_type), 'color' => 'gray'];
                                    $typeColor = $typeInfo['color'];
                                    $typeLabel = $typeInfo['label'];
                                }
                                
                            @endphp
                            @if($typeColor === 'warning')
                                <span class="inline-flex items-center justify-center min-w-20 h-10 px-2 rounded-full text-xs font-medium bg-yellow-100 text-yellow-900 dark:bg-yellow-400 dark:text-yellow-900">
                                    {{ $typeLabel }}
                                </span>
                            @else
                                <span class="inline-flex items-center justify-center min-w-20 h-10 px-2 rounded-full text-xs font-medium bg-{{ $typeColor }}-100 text-{{ $typeColor }}-800 dark:bg-{{ $typeColor }}-900 dark:text-{{ $typeColor }}-200">
                                    {{ $typeLabel }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ number_format((float) $item->amount, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @php
                                $showMaxLimit = in_array($item->transaction_type, ['approved', 'limit_increase', 'limit_decrease']);
                            @endphp
                            @if($showMaxLimit)
                                {{ number_format((float) $item->max_credit_limit_before, 2) }} {{ $currencyCode }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @if($showMaxLimit)
                                {{ number_format((float) $item->max_credit_limit_after, 2) }} {{ $currencyCode }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @php
                                $showCreditUsage = in_array($item->transaction_type, ['credit', 'debit', 'used', 'restored']);
                            @endphp
                            @if($showCreditUsage)
                                {{ number_format((float) $item->available_credit_before, 2) }} {{ $currencyCode }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @if($showCreditUsage)
                                {{ number_format((float) $item->available_credit_after, 2) }} {{ $currencyCode }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $item->description ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            @if($item->related)
                                @php
                                    $related = $item->related;
                                    if ($related instanceof \App\Models\BuyerCreditLimitRequest) {
                                        // For approved credit limit requests, link to buyer
                                        $url = \App\Filament\Resources\BuyerResource::getUrl('view', ['record' => $item->buyer_id]);
                                        $label = $item->buyer->name ?? 'Buyer #' . $item->buyer_id;
                                    } elseif ($related instanceof \App\Models\BuyerOrder) {
                                        // For orders, link to request
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
