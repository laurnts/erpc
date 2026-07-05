<x-mail::message>
# {{ $portalName }} Portal Invitation

You have been invited to access the {{ strtolower($portalName) }} portal for **{{ $companyName }}**.

{{ $portalPitch }}

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
