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

<div>
  <div style="font-size:16px">
    <strong style="color:#3d4852 !important;"> {{ $customer->name  }}</strong> just subscribed
    to <strong style="color:#3d4852 !important;"> {{ $merchant->name }}</strong>.
    You now have a total of  {{ $totalSubscriber }} active subscribers.
  </div>
  <br>

  <strong style="color:#3d4852 !important;">Customer Information</strong>
  <br>
  {{ $customer->name }}
  <br>
  {{ $address }}
  <br>

  <strong style="color:#3d4852 !important;">Overview of Products</strong>
  <br>
  {!! $productDescription !!}

  {!! $paymentType !!}
  <br>

  Head over to <a href='{{$console}}'>{{$console}}</a> to learn more.
</div>

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards'),<br>
{{ config('app.name') }}
@endif
@endcomponent
