@component('mail::message')
@if($team->getEmailLogoUrl())
![Logo]({{ $team->getEmailLogoUrl() }})
@endif

# Test Email

This is a test email from {{ config('app.name') }}.

Your email configuration is working correctly!

**Team:** {{ $team->name }}

@if(!empty($team->getErpSettings()->email_signature))
<br><br>
{!! nl2br(e($team->getErpSettings()->email_signature)) !!}
@endif

Thanks,<br>
{{ $team->getErpSettings()->email_from_name ?: config('app.name') }}
@endcomponent
