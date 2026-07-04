<x-mail::message>
# Application Received

Hi {{ $application->name }},

Thank you for applying for customer portal access for **{{ $application->company_name }}**.

Your application is now awaiting approval by our team. We will notify you by email as soon as a decision has been made. You will not be able to sign in until your application is approved.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
