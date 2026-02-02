@component('mail::layout')
@slot('header')
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
@endslot

{{ $slot }}

@isset($subcopy)
@slot('subcopy')
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
@endslot
@endisset

@slot('footer')
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
@endslot
@endcomponent
