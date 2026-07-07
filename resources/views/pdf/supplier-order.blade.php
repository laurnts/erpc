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
                    {{-- Reference Information --}}
                    @if($order->supplierQuote || $order->request)
                        @if($order->supplierQuote)
                            Quote Reference: {{ $order->supplierQuote->quote_number ?? 'N/A' }}<br>
                        @endif
                        @if($order->request)
                            Request Reference: {{ $order->request->request_number }}<br>
                        @endif
                    @endif
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
            @php
                $displayLines = $order->hierarchicalDisplayLines();
            @endphp
            @forelse($displayLines as $index => $line)
                @php
                    $isChild = $line['is_child'];
                @endphp
                <tr class="{{ $isChild ? 'row-child' : 'row-main' }}">
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="{{ $isChild ? 'child-description' : '' }}">
                        @if($isChild)
                            <span class="child-marker">↳</span>
                        @endif
                        {{ $line['label'] }}
                        @if($line['notes'])
                            <br><small style="color: #6b7280;">{{ $line['notes'] }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($line['quantity'], 0) }}</td>
                    <td class="text-center">{{ $line['unit_label'] }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber($line['unit_price_exc_tax']) ?? number_format($line['unit_price_exc_tax'], 2) }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber($line['line_tax']) ?? number_format($line['line_tax'], 2) }}</td>
                    <td class="text-right">{{ $order->currency?->formatNumber($line['line_total']) ?? number_format($line['line_total'], 2) }}</td>
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

    {{-- Approval Section (only show when approved or sent) --}}
    @if(in_array($order->status, [\App\Enums\OrderStatus::APPROVED, \App\Enums\OrderStatus::SENT]))
        <div style="margin-top: 40px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 25%; text-align: left; padding: 8px; border-bottom: 1px solid #374151; font-size: 9pt; color: #374151; font-weight: bold;">Checked by</th>
                        <th style="width: 25%; text-align: left; padding: 8px; border-bottom: 1px solid #374151; font-size: 9pt; color: #374151; font-weight: bold;">Approved by</th>
                        <th style="width: 25%; text-align: left; padding: 8px; border-bottom: 1px solid #374151; font-size: 9pt; color: #374151; font-weight: bold;">Approved by</th>
                        <th style="width: 25%; text-align: left; padding: 8px; border-bottom: 1px solid #374151; font-size: 9pt; color: #374151; font-weight: bold;">Supplier/Vendor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 20px 8px 8px 8px; vertical-align: bottom; border-bottom: 1px solid #374151;">
                            @php
                                $keyAccounts = $order->request->buyer->keyAccounts ?? collect();
                            @endphp
                            @if($keyAccounts->isNotEmpty())
                                @foreach($keyAccounts as $keyAccount)
                                    <div style="margin-bottom: 5px;">{{ $keyAccount->name }}</div>
                                @endforeach
                            @else
                                <div style="color: #9ca3af;">-</div>
                            @endif
                        </td>
                        <td style="padding: 20px 8px 8px 8px; vertical-align: bottom; border-bottom: 1px solid #374151;">
                            @if($order->approver1)
                                <div>{{ $order->approver1->name }}</div>
                            @else
                                <div style="color: #9ca3af;">-</div>
                            @endif
                        </td>
                        <td style="padding: 20px 8px 8px 8px; vertical-align: bottom; border-bottom: 1px solid #374151;">
                            @if($order->approver2)
                                <div>{{ $order->approver2->name }}</div>
                            @else
                                <div style="color: #9ca3af;">-</div>
                            @endif
                        </td>
                        <td style="padding: 20px 8px 8px 8px; vertical-align: bottom; border-bottom: 1px solid #374151;">
                            <div style="color: #9ca3af;">&nbsp;</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        {{-- Signature Block (for non-approved orders) --}}
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
    @endif
@endsection

@section('footer')
    {{ $company['name'] }} | PO {{ $order->po_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
