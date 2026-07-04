<x-mail::message>
# Application Update

Hi {{ $application->name }},

Thank you for your interest in our customer portal. After reviewing your application for **{{ $application->company_name }}**, we are unable to approve it at this time.

If you believe this is a mistake or your situation changes, you are welcome to get in touch with us or submit a new application.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
