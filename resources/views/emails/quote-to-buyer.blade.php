<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote {{ $quote->quote_number }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <!-- Header with Logo on Left -->
                    <tr>
                        <td style="padding: 30px 30px 10px; border-bottom: 2px solid #3b82f6;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="60%" valign="top" style="vertical-align: top;">
                                        @if($team->getEmailLogoUrl())
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $team->getErpSettings()->company_name ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                    </td>
                                    <td width="40%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">QUOTATION</div>
                                        <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 12px;">{{ $quote->quote_number }}</div>
                                        <div style="font-size: 11px; color: #6b7280; line-height: 1.6;">
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
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Email Body -->
                    <tr>
                        <td style="padding: 30px;">
                            @if(!empty($content) && trim($content) !== '')
                                <div style="font-size: 14px; line-height: 1.6; color: #1f2937;">
                                    {!! nl2br(e($content)) !!}
                                </div>
                            @else
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    Dear {{ $quote->buyer->name ?? 'Buyer' }},
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    Regarding with the request for quotation, below we provide the following item details.
                                </p>
                                
                                @if($quote->items && $quote->items->count() > 0)
                                    <!-- Items Table -->
                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse; border: 1px solid #e5e7eb;">
                                        <thead>
                                            <tr style="background-color: #2563eb;">
                                                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                                                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 45%;">Description</th>
                                                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                                                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 8%;">Unit</th>
                                                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 15%;">Unit Price</th>
                                                <th style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold; color: #ffffff; width: 17%;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($quote->items as $index => $item)
                                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $index + 1 }}</td>
                                                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                                                        {{ $item->description }}
                                                        @if($item->notes)
                                                            <br><small style="color: #6b7280; font-size: 11px;">{{ $item->notes }}</small>
                                                        @endif
                                                    </td>
                                                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                                                        {{ $quote->currency ? $quote->currency->formatNumber((float)$item->quantity) : number_format((float)$item->quantity, 2) }}
                                                    </td>
                                                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                                                        {{ $item->unit_label }}
                                                    </td>
                                                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">
                                                        {{ $quote->currency ? $quote->currency->formatNumber((float)$item->unit_price_exc_tax) : number_format((float)$item->unit_price_exc_tax, 2) }}
                                                    </td>
                                                    <td style="padding: 10px 8px; text-align: right; font-size: 13px; color: #1f2937; font-weight: bold;">
                                                        {{ $quote->currency ? $quote->currency->formatNumber((float)$item->line_total) : number_format((float)$item->line_total, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr style="background-color: #f9fafb; border-top: 2px solid #2563eb;">
                                                <td colspan="4" style="padding: 12px 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                                                    Subtotal:
                                                </td>
                                                <td colspan="2" style="padding: 12px 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                                                    {{ $quote->currency ? $quote->currency->formatNumber((float)$quote->subtotal) : number_format((float)$quote->subtotal, 2) }}
                                                </td>
                                            </tr>
                                            @if((float)$quote->tax_total > 0)
                                                <tr style="background-color: #f9fafb;">
                                                    <td colspan="4" style="padding: 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                                                        Tax:
                                                    </td>
                                                    <td colspan="2" style="padding: 8px; text-align: right; font-size: 13px; font-weight: bold; color: #1f2937;">
                                                        {{ $quote->currency ? $quote->currency->formatNumber((float)$quote->tax_total) : number_format((float)$quote->tax_total, 2) }}
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr style="background-color: #eff6ff; border-top: 2px solid #2563eb;">
                                                <td colspan="4" style="padding: 15px 8px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">
                                                    Grand Total:
                                                </td>
                                                <td colspan="2" style="padding: 15px 8px; text-align: right; font-size: 16px; font-weight: bold; color: #2563eb;">
                                                    {{ $quote->currency ? $quote->currency->formatNumber((float)$quote->total) : number_format((float)$quote->total, 2) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                @endif
                            @endif
                            
                            @if(!empty($team->getErpSettings()->email_signature))
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                                    <div style="font-size: 14px; line-height: 1.6; color: #1f2937; white-space: pre-line;">
                                        {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
                                    </div>
                                </div>
                            @endif
                            
                            <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 20px; margin-bottom: 0;">
                                Thanks,<br>
                                {{ $team->getErpSettings()->email_from_name ?: config('app.name') }}
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center;">
                            <p style="font-size: 12px; color: #6b7280; margin: 0;">
                                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
