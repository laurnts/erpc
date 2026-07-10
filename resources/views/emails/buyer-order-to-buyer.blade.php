<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order {{ $order->order_number }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header Section -->
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
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">{{ $order->order_number }}</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            Order Date: {{ $order->ordered_at ? $order->ordered_at->format('d M Y') : now()->format('d M Y') }}
                                        </div>
                                        <!-- Reference Numbers -->
                                        @if($order->buyerQuote || $order->request)
                                            <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                                @if($order->buyerQuote)
                                                    Quote Reference: <strong style="color: #1f2937;">{{ $order->buyerQuote->quote_number }}@if($order->buyerQuote->version) (v{{ $order->buyerQuote->version }})@endif</strong><br>
                                                @endif
                                                @if($order->request)
                                                    Request Reference: <strong style="color: #1f2937;">{{ $order->request->request_number }}</strong>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Blue Separator Line -->
                    <tr>
                        <td style="padding: 0 30px;">
                            <div style="height: 2px; background-color: #2563eb;"></div>
                        </td>
                    </tr>
                    
                    <!-- BILL TO Section -->
                    <tr>
                        <td style="padding: 25px 30px 20px;">
                            <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $order->buyer->name ?? 'Buyer' }}</div>
                            @if($order->buyer->address)
                                <div style="font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">
                                    {{ $order->buyer->address }}
                                </div>
                            @endif
                            @if($order->buyer->email)
                                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                                    Email: {{ $order->buyer->email }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    
                    <!-- Email Body -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            @if(!empty($content) && trim($content) !== '')
                                <div style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    {!! nl2br(e($content)) !!}
                                </div>
                            @else
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Dear {{ $order->buyer->name ?? 'Buyer' }},
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Please find below the details of your order.
                                </p>
                            @endif
                            
                            @if($order->items && $order->items->count() > 0)
                                @include('emails.partials.buyer-order-items-table', ['order' => $order])
                            @endif
                            
                            @php
                                $paymentTermsLines = $order->payment_terms_lines;
                            @endphp
                            @if($paymentTermsLines !== [])
                                <div style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 25px;">
                                    <strong>Payment Terms:</strong>
                                    <ol style="margin: 8px 0 0 0; padding-left: 20px;">
                                        @foreach($paymentTermsLines as $line)
                                            <li>{{ preg_replace('/^\d+\.\s*/', '', $line) }}</li>
                                        @endforeach
                                    </ol>
                                </div>
                            @elseif($order->payment_terms_days)
                                <p style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 25px;">
                                    <strong>Payment Terms:</strong>
                                    Net {{ $order->payment_terms_days }} days from invoice date
                                </p>
                            @endif
                            
                            @if($order->notes)
                                <p style="font-size: 13px; line-height: 1.6; color: #1f2937; margin-top: 15px;">
                                    {{ $order->notes }}
                                </p>
                            @endif
                            
                            <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px;">
                                Thank you for your order.
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
