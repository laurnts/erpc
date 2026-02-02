@component('mail::message')
@if($team->getEmailLogoUrl())
![Logo]({{ $team->getEmailLogoUrl() }})
@endif

@if(!empty($content))
{!! nl2br(e($content)) !!}
@else
# Quote Request

Dear {{ $quote->supplier->name ?? 'Supplier' }},

We would like to request a quote for the items in Request {{ $quote->request->request_number ?? '' }}.

Please provide your quote at your earliest convenience.
@endif

@if(!empty($team->getErpSettings()->email_signature))
<br><br>
{!! nl2br(e($team->getErpSettings()->email_signature)) !!}
@endif

Thanks,<br>
{{ $team->getErpSettings()->email_from_name ?: config('app.name') }}
@endcomponent
