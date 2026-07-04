<x-mail::message>
# Application Approved

Hi {{ $application->name }},

Your customer portal application for **{{ $application->company_name }}** has been approved.

You can now sign in with the email address and password you chose when applying. You will be asked to verify your email address the first time you sign in.

<x-mail::button :url="$signInUrl">
Sign In to the Customer Portal
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
