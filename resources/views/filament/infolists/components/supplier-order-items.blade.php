@php
    $items = $getRecord()->items;
    $currency = $getRecord()->currency;
@endphp

@if($items->isEmpty())
    <div class="text-gray-500">No items</div>
@else
    <div class="space-y-2">
        @foreach($items as $item)
            <div class="flex justify-between">
                <span>{{ $item->description }}</span>
                <span>
                    {{ number_format((float) $item->quantity, 2) }} × {{ $currency->symbol ?? '' }} {{ number_format((float) $item->line_total, 2) }}
                </span>
            </div>
        @endforeach
    </div>
@endif
