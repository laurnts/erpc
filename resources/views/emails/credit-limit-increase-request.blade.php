<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Limit Increase Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #1f2937; margin-top: 0;">Credit Limit Increase Request</h2>
        
        <p>A new credit limit increase request has been submitted and requires your approval.</p>
        
        <div style="background-color: white; padding: 15px; border-radius: 4px; margin: 15px 0;">
            <h3 style="margin-top: 0; color: #374151;">Request Details</h3>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; width: 40%;">Buyer:</td>
                    <td style="padding: 8px 0;">{{ $buyer->name }} ({{ $buyer->code }})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Current Active Limit:</td>
                    <td style="padding: 8px 0;">{{ $currentLimit }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Requested Limit:</td>
                    <td style="padding: 8px 0; color: #059669; font-weight: bold;">{{ $requestedLimit }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Increase Amount:</td>
                    <td style="padding: 8px 0; color: #059669; font-weight: bold;">{{ $increaseAmount }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Requested By:</td>
                    <td style="padding: 8px 0;">{{ $requester->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Requested At:</td>
                    <td style="padding: 8px 0;">{{ $request->created_at->format('M j, Y g:i A') }}</td>
                </tr>
            </table>
        </div>
        
        <p style="margin-top: 20px;">
            <strong>Action Required:</strong> This request requires approval from 2 finance team members before the credit limit can be updated.
        </p>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="{{ \App\Filament\Resources\BuyerCreditLimitRequestResource::getUrl('index', ['tenant' => $team->getKey()]) }}" 
               style="display: inline-block; background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Review Request
            </a>
        </div>
        
        <p style="margin-top: 30px; font-size: 12px; color: #6b7280;">
            This is an automated notification. Please do not reply to this email.
        </p>
    </div>
</body>
</html>
