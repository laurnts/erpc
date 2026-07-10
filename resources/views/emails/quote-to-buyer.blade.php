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
                                    @include('emails.partials.buyer-quote-items-table', ['quote' => $quote])
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
@include('emails.partials.email-shell-bottom')
