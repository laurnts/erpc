@php
    /** @var string $brandName */
    /** @var string|null $logoUrl */
@endphp

@if (filled($logoUrl))
    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="h-10 max-w-[12rem] object-contain object-left" />
@else
    <span class="text-lg font-semibold tracking-tight">{{ $brandName }}</span>
@endif
