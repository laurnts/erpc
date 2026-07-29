@extends('pdf.layout')

@section('title', 'Invoice ' . $invoice->invoice_number)

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
                <div class="document-title">
                    @if($invoice->type->value === 'credit_note')
                        CREDIT NOTE
                    @elseif($invoice->type->value === 'prepayment')
                        PREPAYMENT INVOICE
                    @else
                        INVOICE
                    @endif
                </div>
                <div class="document-number">{{ $invoice->invoice_number ?? 'DRAFT' }}</div>
                <div class="document-meta">
                    @if($invoice->issued_at)
                        Issue Date: {{ $invoice->issued_at->format('d M Y') }}<br>
                    @endif
                    @if($invoice->due_at)
                        Due Date: {{ $invoice->due_at->format('d M Y') }}<br>
                    @endif
                    <span class="status-badge status-{{ strtolower($invoice->status->value) }}">{{ $invoice->status->getLabel() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Buyer Information --}}
    @php
        $buyer = $invoice->buyerOrder?->buyer ?? $invoice->request?->buyer;
    @endphp
    <div class="party-section">
        <div class="party-box">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $buyer?->name ?? 'N/A' }}</div>
            <div class="party-details">
                @if($buyer?->contact_person)
                    Attn: {{ $buyer->contact_person }}<br>
                @endif
                @if($buyer?->address)
                    {{ $buyer->address }}<br>
                @endif
                @if($buyer?->phone)
                    Tel: {{ $buyer->phone }}<br>
                @endif
                @if($buyer?->email)
                    Email: {{ $buyer->email }}
                @endif
            </div>
        </div>
    </div>

    {{-- Reference Information --}}
    <div class="mb-20">
        <table style="width: 100%; font-size: 9pt;">
            @if($invoice->buyerOrder)
                <tr>
                    <td style="width: 120px; color: #6b7280;"><strong>Order Reference:</strong></td>
                    <td>{{ $invoice->buyerOrder->order_number }}</td>
                </tr>
            @endif
            @if($invoice->request)
                <tr>
                    <td style="width: 120px; color: #6b7280;"><strong>Request Reference:</strong></td>
                    <td>{{ $invoice->request->request_number }}</td>
                </tr>
            @endif
            @if($invoice->originalInvoice)
                <tr>
                    <td style="width: 120px; color: #6b7280;"><strong>Original Invoice:</strong></td>
                    <td>{{ $invoice->originalInvoice->invoice_number ?? 'Not yet issued' }}</td>
                </tr>
            @endif
            @if($invoice->credit_reason)
                <tr>
                    <td style="width: 120px; color: #6b7280;"><strong>Credit Reason:</strong></td>
                    <td>{{ $invoice->credit_reason }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Description</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 12%;">Unit Price</th>
                <th class="text-right" style="width: 8%;">Tax %</th>
                <th class="text-right" style="width: 10%;">Tax</th>
                <th class="text-right" style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-center">{{ number_format((float)$item->quantity, 0) }}</td>
                    <td class="text-right">{{ $invoice->currency?->formatNumber((float)$item->unit_price) ?? number_format((float)$item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format((float)$item->tax_rate, 1) }}%</td>
                    <td class="text-right">{{ $invoice->currency?->formatNumber((float)$item->line_tax) ?? number_format((float)$item->line_tax, 2) }}</td>
                    <td class="text-right">{{ $invoice->currency?->formatNumber((float)$item->line_total) ?? number_format((float)$item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No items</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Totals and Payment Summary --}}
    <div class="two-column">
        <div class="column">
            {{-- Payment History --}}
            @if($invoice->payments->count() > 0)
                <div style="margin-top: 20px;">
                    <div class="notes-label">Payment History</div>
                    <table style="width: 100%; font-size: 9pt; border-collapse: collapse; margin-top: 5px;">
                        <thead>
                            <tr style="background-color: #f1f5f9;">
                                <th style="text-align: left; padding: 5px; border: 1px solid #e5e7eb;">Date</th>
                                <th style="text-align: left; padding: 5px; border: 1px solid #e5e7eb;">Reference</th>
                                <th style="text-align: right; padding: 5px; border: 1px solid #e5e7eb;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->payments as $payment)
                                <tr>
                                    <td style="padding: 5px; border: 1px solid #e5e7eb;">{{ $payment->payment_date?->format('d M Y') ?? '-' }}</td>
                                    <td style="padding: 5px; border: 1px solid #e5e7eb;">{{ $payment->payment_number ?? '-' }}</td>
                                    <td style="text-align: right; padding: 5px; border: 1px solid #e5e7eb;">{{ $invoice->currency?->formatNumber((float)$payment->amount) ?? number_format((float)$payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="column">
            {{-- Totals --}}
            <div class="totals-section" style="width: 100%; margin-left: 0;">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td>{{ $invoice->currency?->format((float)$invoice->subtotal) ?? number_format((float)$invoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Tax:</td>
                        <td>{{ $invoice->currency?->format((float)$invoice->tax_total) ?? number_format((float)$invoice->tax_total, 2) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td>Total:</td>
                        <td>{{ $invoice->currency?->format((float)$invoice->total) ?? number_format((float)$invoice->total, 2) }}</td>
                    </tr>
                    @if((float)$invoice->amount_paid > 0)
                        <tr>
                            <td>Amount Paid:</td>
                            <td style="color: #059669;">{{ $invoice->currency?->format((float)$invoice->amount_paid) ?? number_format((float)$invoice->amount_paid, 2) }}</td>
                        </tr>
                    @endif
                    @if($invoice->amount_outstanding > 0)
                        <tr class="amount-due">
                            <td>Amount Due:</td>
                            <td>{{ $invoice->currency?->format($invoice->amount_outstanding) ?? number_format($invoice->amount_outstanding, 2) }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Payment Instructions --}}
    @if($invoice->amount_outstanding > 0)
        <div class="payment-info">
            <div class="payment-info-title">Payment Instructions</div>
            <div class="payment-info-content">
                Please remit payment within {{ $invoice->net_days }} days of invoice date.<br>
                @if($invoice->due_at)
                    <strong>Payment Due:</strong> {{ $invoice->due_at->format('d M Y') }}
                    @if($invoice->days_overdue > 0)
                        <span style="color: #dc2626;"> ({{ $invoice->days_overdue }} days overdue)</span>
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Notes --}}
    @if($invoice->notes)
        <div class="notes-section">
            <div class="notes-label">Notes</div>
            <div class="notes-content">{{ $invoice->notes }}</div>
        </div>
    @endif
@endsection

@section('footer')
    {{ $company['name'] }} | Invoice {{ $invoice->invoice_number }} | Page 1 of 1 | Generated on {{ now()->format('d M Y H:i') }}
@endsection
