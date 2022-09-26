<table
  role="presentation"
  style="width: auto; margin: 0 auto; border-collapse: collapse;"
>
  <tr>
    {{-- <td style="
      font-weight: bold;
      opacity: 0.64;
      vertical-align: middle;
      {{ $isEmailBlast ? 'padding: 20px 0 20px 0px;' : 'padding: 20px 0 20px 20px;' }}"
    >
      POWERED BY&nbsp;
    </td>

    <td style="padding:0 10px 0 0; vertical-align: middle;">
      <img style="
        max-height: 100%;
        max-width: 100%;
        height: 30px;"
        src="{{ Storage::url("images/assets/helixpay-logo-black.png") }}">
    </td> --}}

    @if ($isEmailBlast)
      {{-- <td style="font-weight:bold;font-size:18px">
        |
      </td> --}}

      <td
        style="
          font-weight: bold;
          opacity: 0.64;
          vertical-align: middle;
        "
      >
        &nbsp;&nbsp;
        <a
          href="{{ $unsubscribeUrl }}"
          target="_blank"
          style="
            text-align: center;
            font-size: 13px;
            text-decoration: none !important;
            color: #9e9e9e !important;
          "
        >
          Unsubscribe
        </a>
      </td>
    @endif
  </tr>
</table>
