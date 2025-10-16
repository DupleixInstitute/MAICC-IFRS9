@component('mail::message')
{!! $body !!}
<br>
Thanks,<br>
{{ config('app.name') }}
@endcomponent
