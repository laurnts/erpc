<x-mail::message>
# Customer Portal Invitation

You have been invited to access the customer portal for **{{ $companyName }}**.

Click the button below to create your account and start submitting goods and services requests on your own.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
