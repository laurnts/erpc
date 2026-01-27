@extends('pdf.layout')

@section('title', 'Purchase Order ' . $order->po_number)

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
                <div class="document-title">PURCHASE ORDER</div>
                <div class="document-number">{{ $order->po_number }}</div>
                <div class="document-meta">
                    @if($order->ordered_at)
                        Order Date: {{ $order->ordered_at->format('d M Y') }}<br>
                    @else
                        Date: {{ now()->format('d M Y') }}<br>
                    @endif
                    @if($order->expected_delivery_date)
                        Expected Delivery: {{ $order->expected_delivery_date->format('d M Y') }}<br>
                    @endif
                    <span class="status-badge status-{{ strtolower($order->status->value) }}">{{ $order->status->getLabel() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Supplier Information --}}
    <div class="party-section">
        <div class="party-box">
            <div class="party-label">Supplier</div>
            <div class="party-name">{{ $order->supplier?->name ?? 'N/A' }}</div>
            <div class="party-details">
                @if($order->supplier?->contact_person)
                    Attn: {{ $order->supplier->contact_person }}<br>
                @endif
                @if($order->supplier?->address)
                    {{ $order->supplier->address }}<br>
                @endif
                @if($order->supplier?->phone)
                    Tel: {{ $order->supplier->phone }}<br>
                @endif
                @if($order->supplier?->email)
                    Email: {{ $order->supplier->email }}
                @endif
            </div>
        </div>
    </div>

    {{-- Reference Information --}}
    @if($order->supplierQuote || $order->request)
        <div class="mb-20">
            <table style="width: 100%; font-size: 9pt;">
                @if($order->supplierQuote)
                    <tr>
                        <td style="width: 120px; color: #6b7280;"><strong>Quote Reference:</strong></td>
                        <td>{{ $order->supplierQuote->quote_number ?? 'N/A' }}</td>
                    </tr>
                @endif
                @if($order->request)
                    <tr>
                        <td style="width: 120px; color: #6b7280;"><strong>Request Reference:</strong></td>
                        <td>{{ $order->request->request_number }}</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Description</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-center" style="width: 8%;">Unit</th>
                <th class="text-right" style="width: 12%;">Unit Price</th>
                <th class="text-right" style="width: 10%;">Tax</th>
                <th class="text-right" style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if($item->notes)
                            <br><small style="color: #6b7280;">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format((float)$item->quantity, 2) }}</td>
                    <td class="text-center">{{ $item->unit_label }}</td>
                    <td class="text-right">{{ number_format((float)$item->unit_price_exc_tax, 2) }}</td>
                    <td class="text-right">{{ number_format((float)($item->tax_amount * $item->quantity), 2) }}</td>
                    <td class="text-right">{{ number_format((float)$item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td>{{ $order->currency?->symbol ?? '' }}{{ number_format((float)$order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td>{{ $order->currency?->symbol ?? '' }}{{ number_format((float)$order->tax_total, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td>Total:</td>
                <td>{{ $order->currency?->symbol ?? '' }}{{ number_format((float)$order->total, 2) }} {{ $order->currency?->code ?? '' }}</td>
            </tr>
        </table>
    </div>

    {{-- Payment Terms --}}
    @if($order->payment_terms_text || $order->payment_terms_days)
        <div class="terms-section">
            <div class="terms-title">Payment Terms</div>
            <div class="terms-content">
                @if($order->payment_terms_text)
                    {{ $order->payment_terms_text }}
                @else
                    Net {{ $order->payment_terms_days }} days from invoice date
                @endif
            </div>
        </div>
    @endif

    {{-- Notes --}}
    @if($order->notes)
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div class="notes-content">{{ $order->notes }}</div>
        </div>
    @endif

    {{-- Signature Block --}}
    <div style="margin-top: 40px;">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <div style="border-top: 1px solid #374151; width: 200px; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Authorized Signature
                    </div>
                </td>
                <td style="width: 50%; vertical-align: bottom; text-align: right;">
                    <div style="border-top: 1px solid #374151; width: 200px; display: inline-block; padding-top: 5px; font-size: 9pt; color: #6b7280;">
                        Date
                    </div>
                </td>
            </tr>
        </table>
    </div>
@endsection

@section('footer')
    {{ $company['name'] }} | PO {{ $order->po_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
