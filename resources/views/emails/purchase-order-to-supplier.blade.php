<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order {{ $order->po_number }}</title>
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
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.6;">
                                            @if($team->getErpSettings()->company_address)
                                                {{ $team->getErpSettings()->company_address }}<br>
                                            @endif
                                            @if($team->getErpSettings()->company_phone)
                                                Tel: {{ $team->getErpSettings()->company_phone }}<br>
                                            @endif
                                            @if($team->getErpSettings()->company_email)
                                                Email: {{ $team->getErpSettings()->company_email }}
                                            @endif
                                        </div>
                                    </td>
                                    <td width="60%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">{{ $order->po_number }}</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            @if($order->ordered_at)
                                                Order Date: {{ $order->ordered_at->format('d M Y') }}<br>
                                            @else
                                                Date: {{ now()->format('d M Y') }}<br>
                                            @endif
                                            @if($order->expected_delivery_date)
                                                Expected Delivery: {{ $order->expected_delivery_date->format('d M Y') }}<br>
                                            @endif
                                        </div>
                                        <!-- Reference Numbers -->
                                        @if($order->supplierQuote || $order->request)
                                            <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                                @if($order->supplierQuote)
                                                    Quote Reference: <strong style="color: #1f2937;">{{ $order->supplierQuote->quote_number ?? 'N/A' }}</strong><br>
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
                    
                    <!-- Supplier Information Section -->
                    <tr>
                        <td style="padding: 25px 30px 20px;">
                            <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $order->supplier->name ?? 'Supplier' }}</div>
                            <div style="font-size: 13px; color: #6b7280; line-height: 1.6;">
                                @if($order->supplier->contact_person)
                                    Attn: {{ $order->supplier->contact_person }}<br>
                                @endif
                                @if($order->supplier->address)
                                    {{ $order->supplier->address }}<br>
                                @endif
                            </div>
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
                                    Dear {{ $order->supplier->name ?? 'Supplier' }},
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Please find below the details of our purchase order.
                                </p>
                            @endif
                            
                            @if($order->hierarchicalDisplayLines()->isNotEmpty())
                                <!-- Items Table -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background-color: #2563eb;">
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                                            <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 40%;">Description</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 8%;">Unit</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 12%;">Unit Price</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Tax</th>
                                            <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; width: 15%;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $currency = $order->currency;
                                        @endphp
                                        @foreach($order->hierarchicalDisplayLines() as $index => $line)
                                            @php
                                                $isChild = $line['is_child'];
                                                $rowBg = $isChild ? '#f9fafb' : ($index % 2 === 0 ? '#ffffff' : '#f9fafb');
                                                $textColor = $isChild ? '#6b7280' : '#1f2937';
                                                $fontWeight = $isChild ? 'normal' : 'bold';
                                            @endphp
                                            <tr style="background-color: {{ $rowBg }}; border-bottom: 1px solid #e5e7eb;">
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                                                <td style="padding: 10px 8px; text-align: left; font-size: {{ $isChild ? '12px' : '13px' }}; color: {{ $textColor }}; border-right: 1px solid #e5e7eb; {{ $isChild ? 'padding-left: 24px;' : '' }}">
                                                    @if($isChild)
                                                        <span style="color: #9ca3af; margin-right: 4px;">↳</span>
                                                    @endif
                                                    {{ $line['label'] }}
                                                    @if($line['notes'])
                                                        <br><small style="color: #6b7280; font-size: 11px;">{{ $line['notes'] }}</small>
                                                    @endif
                                                </td>
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ number_format($line['quantity'], 0) }}</td>
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $line['unit_label'] }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber($line['unit_price_exc_tax']) : number_format($line['unit_price_exc_tax'], 2) }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; border-right: 1px solid #e5e7eb;">{{ $currency ? $currency->formatNumber($line['line_tax']) : number_format($line['line_tax'], 2) }}</td>
                                                <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: {{ $textColor }}; font-weight: {{ $fontWeight }};">{{ $currency ? $currency->format($line['line_total']) : number_format($line['line_total'], 2) }}</td>
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
                                                    <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">Total:</td>
                                                    <td style="padding: 12px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">{{ $currency ? $currency->format((float)$order->total) : number_format((float)$order->total, 2) }}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            
                            @if($order->notes)
                                <p style="font-size: 13px; line-height: 1.6; color: #1f2937; margin-top: 15px;">
                                    {{ $order->notes }}
                                </p>
                            @endif
                            
                            <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px;">
                                Please confirm receipt and expected delivery date.
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
