@php
    /** @var \App\Models\Company $record */
    $record = $getRecord();
    $requests = $record->creditLimitRequests()->orderBy('created_at', 'desc')->get();
    $currencyCode = \Filament\Facades\Filament::getTenant() instanceof \App\Models\Team 
        ? \Filament\Facades\Filament::getTenant()->getBaseCurrencyCode() 
        : 'USD';
@endphp

@if($requests->isEmpty())
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No credit limit requests found.
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Request Date</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Current Limit</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Requested Limit</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Changes</th>
                    <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Status</th>
                    <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">Approvals</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Requested By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $request)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $request->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $request->current_limit, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format((float) $request->requested_limit, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-right text-green-600 dark:text-green-400">
                            {{ number_format((float) $request->requested_limit - (float) $request->current_limit, 2) }} {{ $currencyCode }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                ];
                                $color = $statusColors[$request->status->value] ?? 'gray';
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                                {{ ucfirst($request->status->value) }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $approvalCount = $request->approvalCount();
                                $approvalColor = $approvalCount >= 2 ? 'success' : 'warning';
                            @endphp
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-{{ $approvalColor }}-100 text-{{ $approvalColor }}-800 dark:bg-{{ $approvalColor }}-900 dark:text-{{ $approvalColor }}-200">
                                {{ $approvalCount }}/2
                            </span>
                        </td>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                            {{ $request->requestedBy->name ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
