@component('mail::message')
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Lines --}}
You're receiving this email because you have requested a custom domain setup for your storefront.

**Domain:** {{ $merchant->custom_domain }}

**ELB URL:** {{ $elbUrl }}

**How to Setup:**

1. Open your domain provider's management tool.

2. Add a new DNS record.

3. Enter the details for your CNAME record:

    - **Host Name / Name:** Enter the prefix of your serving domain that you want to add the record for. For example, if your serving domain is 'www.example.com', enter just 'www' there.

    - **Content / Target:** Enter the ELB URL.

    - **TTL:** This determines how long the server should cache content in minutes.

    - **Priority:** This is used to indicate which of the servers listed should attempt to use first, but you may leave this blank instead.

**Example of a CNAME Record:**

@component('mail::table')
| **Key**       | **Value**     |
| :------------ | :------------ |
| **Host Name** | www           |
| **Type**      | CNAME         |
| **Content**   | {{ $elbUrl }} |
| **TTL**       | 60            |
| **Priority**  | N/A           |
@endcomponent

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards'),<br>
{{ config('app.name') }}
@endif
@endcomponent
