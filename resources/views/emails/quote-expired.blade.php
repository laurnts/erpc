<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote {{ $quote->quote_number }} has expired</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 30px 30px 10px; border-bottom: 2px solid #dc2626;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="60%" valign="top" style="vertical-align: top;">
                                        @if($team->getEmailLogoUrl())
                                            <img src="{{ $team->getEmailLogoUrl() }}" alt="{{ $team->getErpSettings()->company_name ?: config('app.name') }}" style="max-width: 150px; height: auto; display: block; margin-bottom: 15px;">
                                        @endif
                                    </td>
                                    <td width="40%" valign="top" align="right" style="vertical-align: top; text-align: right;">
                                        <div style="font-size: 20px; font-weight: bold; color: #dc2626; margin-bottom: 8px;">QUOTE EXPIRED</div>
                                        <div style="font-size: 16px; font-weight: bold; color: #1f2937; margin-bottom: 12px;">{{ $quote->quote_number }}</div>
                                        <div style="font-size: 11px; color: #6b7280; line-height: 1.6;">
                                            Valid until was: {{ $validUntil }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            @if($recipientType === 'buyer')
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    Dear {{ $buyerName }},
                                </p>
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    This is to inform you that quote <strong>{{ $quote->quote_number }}</strong> (valid until {{ $validUntil }}) has expired.
                                </p>
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937;">
                                    If you still wish to proceed, please contact us to request a new quote or an extension.
                                </p>
                            @else
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937; margin-bottom: 15px;">
                                    The buyer quote <strong>{{ $quote->quote_number }}</strong> for {{ $buyerName }} has expired (valid until was {{ $validUntil }}).
                                </p>
                                <p style="font-size: 14px; line-height: 1.6; color: #1f2937;">
                                    You may wish to follow up with the buyer or extend the quote validity if the opportunity is still open.
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
