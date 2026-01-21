@extends('pdf.layout')

@section('title', 'Order Confirmation ' . $order->order_number)

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
                <div class="document-title">ORDER CONFIRMATION</div>
                <div class="document-number">{{ $order->order_number }}</div>
                <div class="document-meta">
                    @if($order->ordered_at)
                        Order Date: {{ $order->ordered_at->format('d M Y') }}<br>
                    @endif
                    @if($order->confirmed_at)
                        Confirmed: {{ $order->confirmed_at->format('d M Y') }}<br>
                    @endif
                    Status: <span class="status-{{ strtolower($order->status->value) }}">{{ $order->status->getLabel() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Buyer Information --}}
    <div class="party-section">
        <div class="party-box">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $order->buyer?->name ?? 'N/A' }}</div>
            <div class="party-details">
                @if($order->buyer?->contact_person)
                    Attn: {{ $order->buyer->contact_person }}<br>
                @endif
                @if($order->buyer?->address)
                    {{ $order->buyer->address }}<br>
                @endif
                @if($order->buyer?->phone)
                    Tel: {{ $order->buyer->phone }}<br>
                @endif
                @if($order->buyer?->email)
                    Email: {{ $order->buyer->email }}
                @endif
            </div>
        </div>
    </div>

    {{-- Reference Information --}}
    @if($order->buyerQuote || $order->request)
        <div class="mb-20">
            <div class="document-meta">
                @if($order->buyerQuote)
                    <strong>Quote Reference:</strong> {{ $order->buyerQuote->quote_number }} (v{{ $order->buyerQuote->version }})<br>
                @endif
                @if($order->request)
                    <strong>Request Reference:</strong> {{ $order->request->request_number }}<br>
                @endif
            </div>
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
                    <td class="text-center">{{ $order->currency?->formatNumber((float)$item->quantity) ?? number_format((float)$item->quantity, 2) }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber((float)$item->unit_price_exc_tax) ?? number_format((float)$item->unit_price_exc_tax, 2) }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber((float)($item->tax_amount * $item->quantity)) ?? number_format((float)($item->tax_amount * $item->quantity), 2) }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber((float)$item->line_total) ?? number_format((float)$item->line_total, 2) }}</td>
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
                <td>{{ $order->currency?->format((float)$order->subtotal) ?? number_format((float)$order->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td>{{ $order->currency?->format((float)$order->tax_total) ?? number_format((float)$order->tax_total, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td>Grand Total:</td>
                <td>{{ $order->currency?->format((float)$order->total) ?? number_format((float)$order->total, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Payment Terms --}}
    @if($order->payment_terms_text || $order->payment_terms_days)
        <div class="payment-info">
            <div class="payment-info-title">Payment Terms</div>
            <div class="payment-info-content">
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
@endsection

@section('footer')
    {{ $company['name'] }} | Order {{ $order->order_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
