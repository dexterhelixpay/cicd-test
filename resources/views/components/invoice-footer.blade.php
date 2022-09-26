<table
role="presentation"
style="
  width: 100%;
  border-collapse: collapse;
  background-color: none;
  max-height: 60px"
>
<tr>
  <td style="
    vertical-align: middle;
    text-transform: uppercase;
    vertical-align: bottom;">
    <p style="font-size: 20px"><strong>{{ $merchant->name }}</strong></p>
    <div style="
        color: #4f4f4f;
        pointer-events: none;
      "
      class="corporate-info"
    >
      @if ($merchant->has_corporate_info_on_invoice)
        {!!
            preg_replace(
              ['/http(.*?)\/\//','/www./','/<a (.*?)>/','/<\/a>/'],
              ['','','',''],
              $merchant->invoice_corporate_info)
        !!}
      @else
        @if ($merchant->website_url)
          {{ rtrim($merchant->website_url, '/') }} <br>
        @endif
        @if ($merchant->instagram_handle)
            {{ "instagram.com/$merchant->instagram_handle" }}
          <br>
        @endif
        @if ($merchant->viber_uri)
          {{
            preg_replace(['/http(.*?)\/\//','/www./'],['',''],$merchant->viber_uri)
          }} <br>
        @endif
      @endif
    </div>
  </td>
  <td style="
    font-weight: bold;
    opacity: 0.64;
    text-transform: uppercase;
    vertical-align: bottom;"
    width="40%"
  >
    POWERED BY&nbsp;
    <img style="
      max-height: 100%;
      max-width: 100%;
      height: 30px;
      vertical-align: text-bottom;"
      src="{{ Storage::url("images/assets/helixpay-logo-black.png") }}">
  </td>
</tr>
</table>
