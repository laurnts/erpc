@php
    $invoice->loadMissing(['items.unitOfMeasure', 'currency', 'buyerOrder.buyer', 'request']);
    $buyer = $invoice->buyerOrder?->buyer ?? $invoice->request?->buyer;
    $buyerOrder = $invoice->buyerOrder;
    $currency = $invoice->currency;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 30px 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="40%" valign="top" style="vertical-align: top;">
                                        @if($team->getEmailLogoUrl())
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $team->getErpSettings()->company_name ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                    </td>
                                    <td width="60%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">{{ $invoice->invoice_number }}</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            Invoice Date: {{ $invoice->issued_at?->format('d M Y') ?? now()->format('d M Y') }}<br>
                                            @if($invoice->due_at)
                                                Due Date: {{ $invoice->due_at->format('d M Y') }}<br>
                                            @endif
                                            @if($buyerOrder)
                                                Order Reference: <strong style="color: #1f2937;">{{ $buyerOrder->order_number }}</strong><br>
                                            @endif
                                            @if($invoice->request)
                                                Request Reference: <strong style="color: #1f2937;">{{ $invoice->request->request_number }}</strong>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 30px;">
                            <div style="height: 2px; background-color: #2563eb;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 25px 30px 20px;">
                            <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $buyer->name ?? 'Buyer' }}</div>
                            @if($buyer?->address)
                                <div style="font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">
                                    {{ $buyer->address }}
                                </div>
                            @endif
                            @if($buyer?->email)
                                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                                    Email: {{ $buyer->email }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 30px 30px;">
                            @if(!empty($content) && trim($content) !== '')
                                <div style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    {!! nl2br(e($content)) !!}
                                </div>
                            @else
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Dear {{ $buyer->name ?? 'Buyer' }},
                                </p>

                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Please find below the details of your invoice.
                                </p>
                            @endif

                            @if($invoice->items && $invoice->items->count() > 0)
                                @include('emails.partials.buyer-invoice-items-table', ['invoice' => $invoice])
                            @endif

                            @if($invoice->net_days)
                                <p style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 25px;">
                                    <strong>Payment Terms:</strong>
                                    Net {{ $invoice->net_days }} days from invoice date
                                </p>
                            @endif

                            @if($invoice->notes)
                                <p style="font-size: 13px; line-height: 1.6; color: #1f2937; margin-top: 15px;">
                                    {{ $invoice->notes }}
                                </p>
                            @endif

                            <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px;">
                                Thank you for your business.
                            </p>

                            @if($team->getErpSettings()->email_signature)
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
                                    {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
