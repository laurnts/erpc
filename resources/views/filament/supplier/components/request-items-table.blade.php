@php
    $state = is_array($state ?? null) ? $state : ($getState() ?? []);
    /** @var \Illuminate\Support\Collection<int, \App\Models\SupplierQuoteItem> $items */
    $items = $state['items'] ?? collect();
    $showPrices = (bool) ($state['showPrices'] ?? false);
    $showResult = (bool) ($state['showResult'] ?? false);
@endphp

@if ($items->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[20rem] text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Quantity</th>
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit</th>
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</th>
                    @if ($showPrices)
                        <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Your Unit Price</th>
                        <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Line Total</th>
                    @endif
                    @if ($showResult)
                        <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Result</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($items as $item)
                    @php $isChild = $item->requestItem?->parent_id !== null; @endphp
                    <tr wire:key="supplier-quote-item-{{ $item->getKey() }}">
                        <td class="px-3 py-2.5 text-gray-900 dark:text-white">{{ $isChild ? '└─ ' : '' }}{{ $item->description }}</td>
                        <td class="px-3 py-2.5 tabular-nums text-gray-900 dark:text-white">{{ number_format((float) $item->quantity, 4) }}</td>
                        <td class="px-3 py-2.5 text-gray-900 dark:text-white">{{ $item->unit_label }}</td>
                        <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">{{ filled($item->notes) ? $item->notes : '—' }}</td>
                        @if ($showPrices)
                            <td class="px-3 py-2.5 tabular-nums text-gray-900 dark:text-white">{{ $item->formatted_unit_price }}</td>
                            <td class="px-3 py-2.5 tabular-nums text-gray-900 dark:text-white">{{ $item->formatted_line_total }}</td>
                        @endif
                        @if ($showResult)
                            <td class="px-3 py-2.5">
                                @if ($isChild)
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @else
                                    <x-filament::badge :color="$item->is_selected ? 'success' : 'gray'">
                                        {{ $item->is_selected ? 'Won' : 'Not selected' }}
                                    </x-filament::badge>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No line items on this request.</p>
@endif
