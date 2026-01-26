@extends('pdf.layout')

@section('title', 'Delivery Order ' . $shipment->do_number)

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-content">
            <div class="company-info">
                <div class="company-name">{{ $company['name'] }}</div>
                <div class="company-details">
                    @if($company['address'])
                        {{ $company['address'] }}<br>
                    @endif
                    @if($company['phone'])
                        Tel: {{ $company['phone'] }}<br>
                    @endif
                    @if($company['email'])
                        Email: {{ $company['email'] }}
                    @endif
                </div>
            </div>
            <div class="document-info">
                <div class="document-title">DELIVERY ORDER</div>
                <div class="document-number">{{ $shipment->do_number }}</div>
                <div class="document-meta">
                    Date: {{ now()->format('d M Y') }}<br>
                    @if($shipment->supplierOrder?->po_number)
                        PO Number: {{ $shipment->supplierOrder->po_number }}<br>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Buyer Information --}}
    @if($shipment->request?->buyer)
        <div class="party-section">
            <div class="party-box">
                <div class="party-label">Buyer</div>
                <div class="party-name">{{ $shipment->request->buyer->name }}</div>
            </div>
        </div>
    @endif

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Item Name</th>
                <th style="width: 15%;">Brand</th>
                <th style="width: 15%;">Model</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th style="width: 25%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="text-center">{{ $item['number'] }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ $item['brand'] ?? '-' }}</td>
                    <td>{{ $item['model'] ?? '-' }}</td>
                    <td class="text-center">{{ number_format((float)$item['qty'], 2) }}</td>
                    <td>{{ $item['remarks'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Delivery Address --}}
    @if($shipment->request?->buyer?->address)
        <div class="party-section" style="margin-top: 30px;">
            <div class="party-box">
                <div class="party-label">Delivery Address</div>
                <div class="party-details">
                    {{ $shipment->request->buyer->name }}<br>
                    @if($shipment->request->buyer->contact_person)
                        Attn: {{ $shipment->request->buyer->contact_person }}<br>
                    @endif
                    {{ $shipment->request->buyer->address }}
                    @if($shipment->request->buyer->phone)
                        <br>Tel: {{ $shipment->request->buyer->phone }}
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Central Purchasing Section --}}
    <div style="margin-top: 40px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 25%; padding: 60px 0 10px;">
                    <div style="border-top: 1px solid #374151; width: 200px; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Prepared By
                    </div>
                </td>
                <td style="width: 25%; padding: 60px 0 10px;">
                    <div style="border-top: 1px solid #374151; width: 200px; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Acknowledged By Head Admin
                    </div>
                </td>
                <td style="width: 25%; padding: 60px 0 10px;">
                    <div style="border-top: 1px solid #374151; width: 200px; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Delivered By
                    </div>
                </td>
                <td style="width: 25%; padding: 60px 0 10px;">
                    <div style="border-top: 1px solid #374151; width: 200px; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Accepted By
                    </div>
                </td>
            </tr>
        </table>
        @if($shipment->notes)
            <div style="margin-top: 20px;">
                <div style="font-size: 9pt; font-weight: bold; color: #4b5563; margin-bottom: 5px;">Notes:</div>
                <div style="font-size: 9pt; color: #6b7280; white-space: pre-wrap;">{{ $shipment->notes }}</div>
            </div>
        @endif
    </div>
@endsection

@section('footer')
    {{ $company['name'] }} | DO {{ $shipment->do_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
