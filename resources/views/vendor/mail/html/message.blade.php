@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => isset($headerUrl) ? $headerUrl : config('app.url')])
  @if (isset($headerImage))
    <img
      style="max-height: 132px !important; max-width: 600px;"
      src="{{ $headerImage }}"
    >
  @elseif (isset($headerText))
      {{ $headerText }}
  @else
    {{ config('app.name') }}
  @endif
@endcomponent
@endslot

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
© {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')
@endcomponent
@endslot
@endcomponent
