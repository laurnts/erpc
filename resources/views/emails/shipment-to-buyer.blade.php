<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Order {{ $shipment->do_number ?? $shipment->shipment_number }}</title>
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
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $company['name'] ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                        @if($company['name'])
                                            <div style="font-size: 16px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">{{ $company['name'] }}</div>
                                        @endif
                                        @if($company['address'])
                                            <div style="font-size: 11px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">{{ $company['address'] }}</div>
                                        @endif
                                        @if($company['phone'])
                                            <div style="font-size: 11px; color: #6b7280; line-height: 1.6; margin-bottom: 4px;">Tel: {{ $company['phone'] }}</div>
                                        @endif
                                        @if($company['email'])
                                            <div style="font-size: 11px; color: #6b7280; line-height: 1.6;">Email: {{ $company['email'] }}</div>
                                        @endif
                                    </td>
                                    <td width="40%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">DELIVERY ORDER</div>
                                        <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 12px;">{{ $shipment->do_number ?? $shipment->shipment_number }}</div>
                                        <div style="font-size: 11px; color: #6b7280; line-height: 1.6;">
                                            Date: {{ now()->format('d M Y') }}<br>
                                            @if($shipment->supplierOrder?->po_number)
                                                PO Number: {{ $shipment->supplierOrder->po_number }}<br>
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
                                    Dear {{ $shipment->request->buyer->name ?? 'Buyer' }},
                                </p>
                                
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    Please find below the delivery order details for your shipment.
                                </p>
                                
                                @if($items && $items->count() > 0)
                                    <!-- Items Table -->
                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse; border: 1px solid #e5e7eb;">
                                        <thead>
                                            <tr style="background-color: #2563eb;">
                                                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 5%;">#</th>
                                                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 30%;">Item Name</th>
                                                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 15%;">Brand</th>
                                                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 15%;">Model</th>
                                                <th style="padding: 12px 8px; text-align: center; font-size: 12px; font-weight: bold; color: #ffffff; border-right: 1px solid #1e40af; width: 10%;">Qty</th>
                                                <th style="padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; color: #ffffff; width: 25%;">Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($items as $item)
                                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item['number'] }}</td>
                                                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item['item_name'] }}</td>
                                                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item['brand'] ?? '-' }}</td>
                                                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ $item['model'] ?? '-' }}</td>
                                                    <td style="padding: 10px 8px; text-align: center; font-size: 13px; color: #1f2937; border-right: 1px solid #e5e7eb;">{{ number_format((float)$item['qty'], 2) }}</td>
                                                    <td style="padding: 10px 8px; text-align: left; font-size: 13px; color: #1f2937;">{{ $item['remarks'] ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif

                                @if($shipment->request?->buyer?->address)
                                    <!-- Delivery Address -->
                                    <div style="margin-top: 20px; padding: 15px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px;">
                                        <div style="font-size: 13px; font-weight: bold; color: #1f2937; margin-bottom: 8px;">Delivery Address:</div>
                                        <div style="font-size: 13px; line-height: 1.6; color: #1f2937;">
                                            {{ $shipment->request->buyer->name }}<br>
                                            @if($shipment->request->buyer->contact_person)
                                                Attn: {{ $shipment->request->buyer->contact_person }}<br>
                                            @endif
                                            {{ $shipment->request->buyer->address }}
                                            @if($shipment->request->buyer->phone)
                                                <br>Tel: {{ $shipment->request->buyer->phone }}
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if($shipment->tracking_number)
                                    <div style="margin-top: 15px; font-size: 13px; color: #1f2937;">
                                        <strong>Tracking Number:</strong> {{ $shipment->tracking_number }}
                                    </div>
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
