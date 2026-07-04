<x-mail::message>
# Supplier Portal Invitation

You have been invited to access the supplier portal for **{{ $companyName }}**.

Click the button below to create your account, maintain your article prices and availability, and respond to quote requests.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
