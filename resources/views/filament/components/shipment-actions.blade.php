@php
    $shipmentId = $shipment->id;
    $isPending = $shipment->status === \App\Enums\ShipmentStatus::PENDING;
    $isInTransit = $shipment->status === \App\Enums\ShipmentStatus::IN_TRANSIT;
    $isPartial = $shipment->status === \App\Enums\ShipmentStatus::PARTIAL;
    $isResend = $shipment->do_sent_at !== null;
    $buttonLabel = $isResend ? 'Resend DO' : 'Send DO';
    $pdfUrl = route('shipment.pdf', ['shipment' => $shipmentId]);
@endphp

<div class="flex gap-2 flex-wrap">
    <a href="{{ $pdfUrl }}" target="_blank" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Download DO
    </a>
    
    @if($isPending)
        <button type="button" wire:click="shipShipment({{ $shipmentId }})" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
            </svg>
            Ship
        </button>
    @endif
    
    @if($isInTransit || $isPartial)
        <button type="button" wire:click="deliverShipment({{ $shipmentId }})" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Deliver
        </button>
        
        <button type="button" wire:click="sendDeliveryOrder({{ $shipmentId }})" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
            </svg>
            {{ $buttonLabel }}
        </button>
    @endif
</div>
