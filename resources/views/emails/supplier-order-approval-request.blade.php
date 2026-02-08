<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Order Approval Required: {{ $order->po_number }}</title>
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
                                        <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">Approval Required</div>
                                        <div style="font-size: 12px; color: #6b7280; line-height: 1.8;">
                                            Date: {{ now()->format('d M Y') }}<br>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Content Section -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <div style="font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 20px;">
                                Hello {{ $approver->name }},
                            </div>

                            <div style="font-size: 14px; color: #374151; line-height: 1.8; margin-bottom: 20px;">
                                A supplier order requires your approval before it can be sent to the supplier.
                            </div>

                            <!-- Order Details Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
                                <tr>
                                    <td>
                                        <div style="font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 12px;">Order Details:</div>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">PO Number:</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #1f2937; font-weight: 500;">{{ $order->po_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Supplier:</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #1f2937; font-weight: 500;">{{ $order->supplier->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Request:</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #1f2937; font-weight: 500;">{{ $order->request->request_number ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Total Amount:</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #1f2937; font-weight: 500;">{{ $order->formatted_total }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Created:</td>
                                                <td style="padding: 4px 0; font-size: 13px; color: #1f2937; font-weight: 500;">{{ $order->created_at->format('M j, Y') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <div style="font-size: 14px; color: #374151; line-height: 1.8; margin-bottom: 25px;">
                                This order has been confirmed and requires approval from at least 2 approvers with roles: <strong>Dept Head of Sales</strong>, <strong>Deputy Director</strong>, or <strong>Director</strong>.
                            </div>

                            <!-- Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="{{ $approvalUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500;">
                                            View Order & Approve
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <div style="font-size: 12px; color: #6b7280; text-align: center; line-height: 1.6;">
                                Thank you,<br>
                                <strong style="color: #1f2937;">{{ $team->name ?? config('app.name') }}</strong>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
