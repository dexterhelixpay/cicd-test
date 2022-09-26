<x-layouts.master is-email-blast="true" :merchant="$merchant" :customer="$customer">
  <table
    role="presentation"
    style="width: 100%; border-collapse: collapse; {{ $bgColor }}"
  >
    <x-header-banner
      :bg-color="$headerBgColor"
      :image-path="$merchant->logo_image_path"
    />

    <tr>
      <td style="padding: 20px;">
        <div style="
          font-size: {{ data_get($fontCss, 'headline.font-size') ?? '16px' }};
          font-family: {{ data_get($fontCss, 'headline.font-family') ? data_get($fontCss, 'headline.font-family') . ', Roboto, Arial, sans-serif' : 'inherit' }};
          line-height: {{ data_get($fontCss, 'headline.line-height') ?? '1.5' }};
          font-weight: {{ data_get($fontCss, 'headline.font-weight') ??  '700' }};
          color: {{ $merchant->on_background_color ?? '#2f2f2f' }} !important"
        >
          {{ $title }}
        </div>

        <div style="
          font-size: {{ data_get($fontCss, 'subtitle.font-size') ?? '16px' }};
          font-family: {{ data_get($fontCss, 'subtitle.font-family') ? data_get($fontCss, 'subtitle.font-family') . ', Roboto, Arial, sans-serif' : 'inherit' }};
          line-height: {{ data_get($fontCss, 'subtitle.line-height') ?? '1.5' }};
          font-weight: {{ data_get($fontCss, 'subtitle.font-weight') ?? '400' }};
          margin-top: 16px;
          color: {{ $merchant->on_background_color ?? '#2f2f2f' }} !important"
        >
          {{ $subtitle }}
        </div>

        @if (!empty($bannerUrl))
          <a href="{{ $bannerUrl }}"
            target="_blank"
            style="
              display: block;
              position: relative;
              margin-top: 15px"
            >
              <img style="border-radius: 5px" width="100%"
                src="{{ $bannerImagePath }}"
              />
          </a>
        @else
          <img style="border-radius: 5px; margin-top:15px" width="100%"
            src="{{ $bannerImagePath }}"
          />
        @endif

        @if ($body)
          <div style="
            margin-top: 15px;
            {{ data_get($fontCss, 'body.line-height') ? 'line-height: '.data_get($fontCss, 'body.line-height').';' : '' }}
            {{ data_get($fontCss, 'body.font-size') ? 'font-size: '.data_get($fontCss, 'body.font-size').';' : '' }}
            {{ data_get($fontCss, 'body.font-family') ? 'font-family: '.data_get($fontCss, 'body.font-family').', Roboto, Arial, sans-serif;' : '' }}
            {{ data_get($fontCss, 'body.font-weight') ? 'font-weight: '.data_get($fontCss, 'body.font-weight').';' : '' }}
            color: {{ $merchant->on_background_color ?? '#2f2f2f' }} !important"
          >
            {!! $body !!}
          </div>
        @endif
      </td>
    </tr>
  </table>
</x-layouts.master>
