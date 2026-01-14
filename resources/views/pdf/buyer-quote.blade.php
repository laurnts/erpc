@extends('pdf.layout')

@section('title', 'Quote ' . $quote->quote_number)

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
                <div class="document-title">QUOTATION</div>
                <div class="document-number">{{ $quote->quote_number }}</div>
                <div class="document-meta">
                    Version: {{ $quote->version }}<br>
                    @if($quote->issued_at)
                        Date: {{ $quote->issued_at->format('d M Y') }}<br>
                    @else
                        Date: {{ now()->format('d M Y') }}<br>
                    @endif
                    @if($quote->valid_until)
                        Valid Until: {{ $quote->valid_until->format('d M Y') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Buyer Information --}}
    <div class="party-section">
        <div class="party-box">
            <div class="party-label">Quote For</div>
            <div class="party-name">{{ $quote->buyer?->name ?? 'N/A' }}</div>
            <div class="party-details">
                @if($quote->buyer?->contact_person)
                    Attn: {{ $quote->buyer->contact_person }}<br>
                @endif
                @if($quote->buyer?->address)
                    {{ $quote->buyer->address }}<br>
                @endif
                @if($quote->buyer?->phone)
                    Tel: {{ $quote->buyer->phone }}<br>
                @endif
                @if($quote->buyer?->email)
                    Email: {{ $quote->buyer->email }}
                @endif
            </div>
        </div>
    </div>

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
            @forelse($quote->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if($item->notes)
                            <br><small style="color: #6b7280;">{{ $item->notes }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ $quote->currency?->formatNumber((float)$item->quantity) ?? number_format((float)$item->quantity, 2) }}</td>
                    <td class="text-center">{{ $item->unit }}</td>
                    <td class="text-right">{{ $quote->currency?->formatNumber((float)$item->unit_price_exc_tax) ?? number_format((float)$item->unit_price_exc_tax, 2) }}</td>
                    <td class="text-right">{{ $quote->currency?->formatNumber((float)$item->line_tax) ?? number_format((float)$item->line_tax, 2) }}</td>
                    <td class="text-right">{{ $quote->currency?->formatNumber((float)$item->line_total) ?? number_format((float)$item->line_total, 2) }}</td>
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
                <td>{{ $quote->currency?->format((float)$quote->subtotal) ?? number_format((float)$quote->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td>{{ $quote->currency?->format((float)$quote->tax_total) ?? number_format((float)$quote->tax_total, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td>Grand Total:</td>
                <td>{{ $quote->currency?->format((float)$quote->total) ?? number_format((float)$quote->total, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Payment Terms --}}
    @if($quote->prepayment_percent > 0 || $quote->payment_terms_description)
        <div class="payment-info">
            <div class="payment-info-title">Payment Terms</div>
            <div class="payment-info-content">
                @if($quote->prepayment_percent > 0)
                    <strong>Prepayment Required:</strong> {{ $quote->prepayment_percent }}% ({{ $quote->currency?->format((float)$quote->total * $quote->prepayment_percent / 100) ?? number_format((float)$quote->total * $quote->prepayment_percent / 100, 2) }})<br>
                @endif
                @if($quote->payment_terms_description)
                    {{ $quote->payment_terms_description }}
                @else
                    Net {{ $quote->payment_terms_days }} days
                @endif
            </div>
        </div>
    @endif

    {{-- Notes --}}
    @if($quote->notes)
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div class="notes-content">{{ $quote->notes }}</div>
        </div>
    @endif

    {{-- Terms and Conditions --}}
    @if($quote->terms_and_conditions)
        <div class="terms-section">
            <div class="terms-title">Terms and Conditions</div>
            <div class="terms-content">{{ $quote->terms_and_conditions }}</div>
        </div>
    @endif
@endsection

@section('footer')
    {{ $company['name'] }} | Quote {{ $quote->quote_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
