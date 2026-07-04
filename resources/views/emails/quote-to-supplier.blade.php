<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Request - {{ $quote->request->request_number ?? '' }}</title>
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
                                    <td width="60%" valign="top" style="vertical-align: top;">
                                        @if($team->getEmailLogoUrl())
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $team->getErpSettings()->company_name ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                    </td>
                                    <td width="40%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">Quote Request</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            Request Number: <strong style="color: #1f2937;">{{ $quote->request->request_number ?? 'N/A' }}</strong><br>
                                            Quote Number: <strong style="color: #1f2937;">{{ $quote->quote_number ?? 'N/A' }}</strong><br>
                                            @if($quote->valid_until)
                                                Valid Until: {{ $quote->valid_until->format('d M Y') }}<br>
                                            @endif
                                            Date: {{ now()->format('d M Y') }}
                                        </div>
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
                            <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">{{ $quote->supplier->name ?? 'Supplier' }}</div>
                            <div style="font-size: 13px; color: #6b7280; line-height: 1.6;">
                                @if($quote->supplier->contact_person)
                                    Attn: {{ $quote->supplier->contact_person }}<br>
                                @endif
                                @if($quote->supplier->address)
                                    {{ $quote->supplier->address }}<br>
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
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    Dear {{ $quote->supplier->name ?? 'Supplier' }},
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    We would like to request a quote for the items in Request <strong>{{ $quote->request->request_number ?? '' }}</strong>.
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 20px;">
                                    Please provide your quote at your earliest convenience.
                                </p>
                            @endif
                            
                            @if($quote->items && $quote->items->count() > 0)
                                <!-- Items Table -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background-color: #2563eb;">
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                                            <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 50%;">Description</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                                            <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; width: 35%;">Unit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($quote->items as $index => $item)
                                            @php
                                                $rowBg = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
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
                                                <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937;">{{ $item->unit_label ?? $item->unit ?? 'pcs' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            
                            @if(!empty($portalUrl))
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 25px 0 5px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $portalUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 14px; font-weight: bold; text-decoration: none; padding: 12px 28px; border-radius: 6px;">Respond in the Supplier Portal</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="center" style="padding-top: 10px;">
                                            <span style="font-size: 12px; color: #6b7280;">Submit your prices or decline this request online.</span>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if($team->getErpSettings()->email_signature)
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">
                                    {!! nl2br(e($team->getErpSettings()->email_signature)) !!}
                                </div>
                            @endif
                            
                            <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-top: 25px; margin-bottom: 0;">
                                Thanks,<br>
                                {{ $team->getErpSettings()->email_from_name ?: config('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
