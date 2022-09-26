<style>
  table, td, div, h1, p {font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',sans-serif;}
  a:link, a:visited {text-decoration: none;}
  a:hover {text-decoration: underline !important;}
  body, button, a {
   pointer-events:  {{ isset($isTestEmail) && $isTestEmail ? 'none;' : 'auto'}}
  }
</style>

<table
  role="presentation"
  summary="body"
  align="center"
  border="0"
  cellspacing="0"
  cellpadding="0"
  style="width:100%;height:100%; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',sans-serif; font-size: 14px; border-collapse: collapse;"
>
  <tr>
    <td style="padding: 0;" align="center" valign="top">
      <table
        role="presentation"
        style="background-color: #ffffff!important;
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        border-collapse: collapse;"
      >
        <tr>
          <td style="padding: 0;" align="center">
            {{ $slot }}
            @if (isset($customer))
              <x-footer :is-email-blast="isset($isEmailBlast)" :merchant="$merchant ?? null" :customer="$customer ?? null"/>
            @endif
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
