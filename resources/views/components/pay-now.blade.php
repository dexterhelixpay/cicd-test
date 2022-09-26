<table
  role="presentation"
  style="
  width: 100%;
  border-collapse: separate;
  border-spacing: 15px;
  background-color: white;
  border-radius: 5px;"
>
  <tr>
    <td style="
      padding: 0;
      text-align: left;
      font-weight: bold;
      font-size: 16px;"
    >
      Payment Due
    </td>
    <td style="
      padding: 0;
      text-align: right;
      font-weight: bold;
      font-size: 16px;"
    >
      ₱{{ number_format($duePrice, 2) }}
    </td>
  </tr>

  <tr>
      <td colspan="2" style="
        padding: 0;
        font-style: italic !important;
        font-weight: 600px !important;
        font-size: 14px !important;
        font-weight: medium;
        font-size: 16px;"
      >
        Pay now to {{ $startOrContinue }} your {{ $merchant->subscription_term_singular }}
      </td>
  </tr>

  <tr>
    <td
      style="padding: 0;
      font-weight: medium;
      font-style: italic !important;
      font-size: 16px;"
      colspan="2"
    >
       <div style="display: inline-block; vertical-align: middle;" >
          @foreach(array_slice($paymentTypeImages, 0, 6) as $imagePath)
              <img
                  style="height: 18px; border-radius: 5px"
                  src="{{ $imagePath }}"
              />
          @endforeach
          & more
        </div>
    </td>
  </tr>
</table>
