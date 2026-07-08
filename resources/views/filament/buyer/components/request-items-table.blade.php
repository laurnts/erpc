@php
    /** @var \App\Models\Request|null $record */
    $record = $getRecord();
    $items = $record?->items ?? collect();
@endphp

@if ($items->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[20rem] text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Description</th>
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Quantity</th>
                    <th class="px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($items as $item)
                    <tr wire:key="request-item-{{ $item->getKey() }}">
                        <td class="px-3 py-2.5 text-gray-900 dark:text-white">{{ $item->parent_id !== null ? '└─ ' : '' }}{{ $item->description }}</td>
                        <td class="px-3 py-2.5 tabular-nums text-gray-900 dark:text-white">{{ number_format((float) $item->quantity, 4) }}</td>
                        <td class="px-3 py-2.5 text-gray-900 dark:text-white">{{ $item->unitOfMeasure?->label ?? $item->unit?->value ?? 'pcs' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No line items on this request.</p>
@endif
