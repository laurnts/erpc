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
                <th style="width: 45%;">Description</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-center" style="width: 8%;">Unit</th>
                <th class="text-right" style="width: 15%;">Unit Price</th>
                <th class="text-right" style="width: 17%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items ?? $quote->items as $index => $item)
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
                    <td class="text-right">{{ $quote->currency?->formatNumber((float)$item->line_total) ?? number_format((float)$item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-section">
        <table class="totals-table">
            <tbody>
                <tr>
                    <td>Subtotal:</td>
                    <td>{{ $quote->currency?->format((float)($processedSubtotal ?? $quote->subtotal)) ?? number_format((float)($processedSubtotal ?? $quote->subtotal ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Tax:</td>
                    <td>{{ $quote->currency?->format((float)($processedTaxTotal ?? $quote->tax_total)) ?? number_format((float)($processedTaxTotal ?? $quote->tax_total ?? 0), 2) }}</td>
                </tr>
                <tr class="grand-total">
                    <td>Grand Total:</td>
                    <td>{{ $quote->currency?->format((float)($processedTotal ?? $quote->total)) ?? number_format((float)($processedTotal ?? $quote->total ?? 0), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Terms and Conditions --}}
    @if($quote->terms_and_conditions)
        <div class="terms-section">
            <div class="terms-title">Terms and Conditions</div>
            <div class="terms-content">{{ $quote->terms_and_conditions }}</div>
        </div>
    @endif

    {{-- Notes --}}
    @if($quote->notes)
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div class="notes-content">{{ $quote->notes }}</div>
        </div>
    @endif
@endsection

@section('footer')
    {{ $company['name'] }} | Quote {{ $quote->quote_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
