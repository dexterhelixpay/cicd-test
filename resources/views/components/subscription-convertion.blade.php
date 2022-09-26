<table
  role="presentation"
  style="
  width: 100%;
  border-collapse: separate;
  border-spacing: 15px;
  background-color: white;
  border-radius: 5px;"
>
<tbody style="width: 330px !important">
  <tr>
    <td style="
      padding: 0;
      text-align: center;
      font-weight: bold;
      font-size: 16px;">
      {{ $merchant->single_recurrence_title ?? "Try a {$merchant->subscription_term_singular}?" }} <br>
      <p style="margin:0 !important; font-weight:normal !important; color:grey !important; font-size: 12px !important;">
        {{ $merchant->single_recurrence_subtitle ?? "You can easily change your order to a {$merchant->subscription_term_singular}!" }}
      </p>
    </td>
  </tr>
  <tr>
      <td style="padding: 0; text-align: center;" colspan="2">
        <a href="{{ $editUrl }}"
          target="_blank"
          style="
            text-align: center;
            font-weight: bolder;
            font-size: 16px;
            color: white !important;
            text-decoration:none !important;
            {{ $buttonColor }}
            padding: 8px 35px;
            cursor: pointer;
            display: inline-block !important;
            border-radius: 5px;"
        >
          {{ $merchant->single_recurrence_button_text ?? 'Create a '.ucfirst($merchant->subscription_term_singular) }}
        </a>
      </td>
    </tr>
  </tbody>
</table>
