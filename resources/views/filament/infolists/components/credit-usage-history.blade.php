@php
    /** @var \App\Models\Company $record */
    $record = $getRecord()->load('defaultCurrency');
    $history = $record->creditUsageHistory()->orderBy('created_at', 'desc')->get();
    // Get currency object from buyer's default currency, fallback to team base currency
    $currency = $record->defaultCurrency 
        ?? (\Filament\Facades\Filament::getTenant() instanceof \App\Models\Team 
            ? \Filament\Facades\Filament::getTenant()->getBaseCurrency() 
            : null);
    $currencyCode = $currency?->code ?? 'USD';
    $symbolPosition = $currency?->symbol_position ?? 'before';
@endphp

{{-- Ensure Tailwind compiles badge classes --}}
<div class="hidden bg-emerald-200 text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100 bg-orange-200 text-orange-900 dark:bg-orange-800 dark:text-orange-100 bg-green-200/70 text-green-900 dark:bg-green-200/50 dark:text-green-100 bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200 bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200"></div>

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
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Approved By</th>
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
                                        $badgeClass = 'bg-emerald-200 text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100';
                                    } elseif ($maxAfter < $maxBefore) {
                                        $typeLabel = 'Limit Decrease';
                                        $badgeClass = 'bg-orange-200 text-orange-900 dark:bg-orange-800 dark:text-orange-100';
                                    } else {
                                        $typeLabel = 'Approved';
                                        $badgeClass = 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200';
                                    }
                                } else {
                                    $typeMap = [
                                        'limit_increase' => ['label' => 'Limit Increase', 'class' => 'bg-emerald-200 text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100'],
                                        'limit_decrease' => ['label' => 'Limit Decrease', 'class' => 'bg-orange-200 text-orange-900 dark:bg-orange-800 dark:text-orange-100'],
                                        'credit' => ['label' => 'Credit', 'class' => 'bg-green-200/70 text-green-900 dark:bg-green-200/50 dark:text-green-100'],
                                        'debit' => ['label' => 'Debit', 'class' => 'bg-green-200/70 text-green-900 dark:bg-green-200/50 dark:text-green-100'],
                                        'used' => ['label' => 'Used', 'class' => 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200'], // Legacy
                                        'restored' => ['label' => 'Restored', 'class' => 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200'], // Legacy
                                    ];
                                    $typeInfo = $typeMap[$item->transaction_type] ?? ['label' => ucfirst($item->transaction_type), 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'];
                                    $badgeClass = $typeInfo['class'];
                                    $typeLabel = $typeInfo['label'];
                                }
                            @endphp
                            <span class="inline-flex items-center justify-center min-w-[80px] h-10 px-2 rounded-full text-xs font-medium {{ $badgeClass }}">
                                {{ $typeLabel }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            @php
                                $formattedAmount = $currency ? $currency->formatNumber((float) $item->amount) : number_format((float) $item->amount, 2);
                                $displayAmount = $symbolPosition === 'after' ? $formattedAmount . ' ' . $currencyCode : $currencyCode . ' ' . $formattedAmount;
                            @endphp
                            {{ $displayAmount }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @php
                                $showMaxLimit = in_array($item->transaction_type, ['approved', 'limit_increase', 'limit_decrease']);
                            @endphp
                            @if($showMaxLimit)
                                @php
                                    $formattedAmount = $currency ? $currency->formatNumber((float) $item->max_credit_limit_before) : number_format((float) $item->max_credit_limit_before, 2);
                                    $displayAmount = $symbolPosition === 'after' ? $formattedAmount . ' ' . $currencyCode : $currencyCode . ' ' . $formattedAmount;
                                @endphp
                                {{ $displayAmount }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @if($showMaxLimit)
                                @php
                                    $formattedAmount = $currency ? $currency->formatNumber((float) $item->max_credit_limit_after) : number_format((float) $item->max_credit_limit_after, 2);
                                    $displayAmount = $symbolPosition === 'after' ? $formattedAmount . ' ' . $currencyCode : $currencyCode . ' ' . $formattedAmount;
                                @endphp
                                {{ $displayAmount }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @php
                                $showCreditUsage = in_array($item->transaction_type, ['credit', 'debit', 'used', 'restored', 'approved', 'limit_increase', 'limit_decrease']);
                            @endphp
                            @if($showCreditUsage)
                                @php
                                    $formattedAmount = $currency ? $currency->formatNumber((float) $item->available_credit_before) : number_format((float) $item->available_credit_before, 2);
                                    $displayAmount = $symbolPosition === 'after' ? $formattedAmount . ' ' . $currencyCode : $currencyCode . ' ' . $formattedAmount;
                                @endphp
                                {{ $displayAmount }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            @if($showCreditUsage)
                                @php
                                    $formattedAmount = $currency ? $currency->formatNumber((float) $item->available_credit_after) : number_format((float) $item->available_credit_after, 2);
                                    $displayAmount = $symbolPosition === 'after' ? $formattedAmount . ' ' . $currencyCode : $currencyCode . ' ' . $formattedAmount;
                                @endphp
                                {{ $displayAmount }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $item->description ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            @php
                                $isLimitChange = in_array($item->transaction_type, ['approved', 'limit_increase', 'limit_decrease']);
                            @endphp
                            @if($isLimitChange)
                                —
                            @elseif($item->related)
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
                            @php
                                $isCreditDebit = in_array($item->transaction_type, ['credit', 'debit']);
                                $isLimitChange = in_array($item->transaction_type, ['approved', 'limit_increase', 'limit_decrease']);
                            @endphp
                            @if($isCreditDebit)
                                —
                            @elseif($isLimitChange && $item->related instanceof \App\Models\BuyerCreditLimitRequest)
                                @php
                                    $approvals = $item->related->approvals()->with('user')->orderBy('approved_at', 'asc')->get();
                                @endphp
                                @if($approvals->count() > 0)
                                    @foreach($approvals as $index => $approval)
                                        {{ $approval->user->name ?? '—' }}@if($index < $approvals->count() - 1) - @endif@if($index < $approvals->count() - 1)<br>@endif
                                    @endforeach
                                @else
                                    —
                                @endif
                            @else
                                {{ $item->createdBy->name ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
