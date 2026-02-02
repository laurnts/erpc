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
                                <!-- Items Table -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background-color: #2563eb;">
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                                            <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 40%;">Description</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 8%;">Unit</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 12%;">Unit Price</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 12%;">Tax</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; width: 13%;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $currency = $order->buyerQuote?->currency;
                                        @endphp
                                        @foreach($order->items as $index => $item)
                                            @php
                                                $rowBg = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                                $lineTax = (float)$item->tax_amount * (float)$item->quantity;
                                            @endphp
                                            <tr style="background-color: {{ $rowBg }}; border-bottom: 1px solid #e5e7eb;">
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                                                <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                                                    {{ $item->description }}
                                                    @if($item->notes)
                                                        <br><small style="color: #6b7280; font-size: 11px;">{{ $item->notes }}</small>
                                                    @endif
                                                </td>
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item->quantity }}</td>
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item->unit_label }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber((float)$item->unit_price_exc_tax) : number_format((float)$item->unit_price_exc_tax, 2) }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber($lineTax) : number_format($lineTax, 2) }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; font-weight: bold;">{{ $currency ? $currency->formatNumber((float)$item->line_total) : number_format((float)$item->line_total, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                
                                <!-- Summary Section (Right-aligned) -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
                                    <tr>
                                        <td align="right">
                                            <table cellpadding="0" cellspacing="0" style="margin-left: auto; width: 300px;">
                                                <tr>
                                                    <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">Subtotal:</td>
                                                    <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937; width: 120px;">{{ $currency ? $currency->format((float)$order->subtotal) : number_format((float)$order->subtotal, 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">Tax:</td>
                                                    <td style="padding: 8px 12px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">{{ $currency ? $currency->format((float)$order->tax_total) : number_format((float)$order->tax_total, 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" style="padding: 8px 0;">
                                                        <div style="height: 2px; background-color: #2563eb;"></div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">Grand Total:</td>
                                                    <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">{{ $currency ? $currency->format((float)$order->total) : number_format((float)$order->total, 2) }}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            
                            @if($order->payment_terms_text || $order->payment_terms_days)
                                <p style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 25px;">
                                    <strong>Payment Terms:</strong>
                                    @if($order->payment_terms_text)
                                        {{ $order->payment_terms_text }}
                                    @else
                                        Net {{ $order->payment_terms_days }} days from invoice date
                                    @endif
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
