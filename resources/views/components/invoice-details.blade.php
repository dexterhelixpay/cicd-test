<table
  role="presentation"
  style="
    width: 100%;
    border-spacing: 0px 10px;"
>
  <tr>
    <td style="
      padding: 0;
      text-align: left;
      font-weight: 700;
      font-size: 55px;"
    >
      Invoice
    </td>
      <td style="
        font-size: 25px;
        font-weight: 700;
        text-align: right;
        vertical-align: bottom;"
      >
        Bill To
      </td>
  </tr>

  <tr  style="
    font-size: 16px;
    line-height: 25px;
    font-weight: 400;
    letter-spacing: 2px;
    margin-top: 10px"
  >
    <td>
      <div> INVOICE NO. {{ $invoiceNumber }} </div>
      <div> SUBSCRIPTION NO. {{ $subscription->id }} </div>
      <div> ORDER NO. {{ formatId($order->created_at, $order->id) }} </div>
      <div> BILLING DATE: {{ date_format($order->billing_date, 'm/d/y') }} </div>
      <div> PAYMENT DATE: {{ $order->paid_at ? date_format($order->paid_at, 'm/d/y') : '' }} </div>
    </td>

      <td>
        <div style="text-align: right; text-transform: uppercase;">
        {{ $customer->name }}<br>
        {{ $customer->formatted_mobile_number }}<br>
        @if ($subscription->merchant->is_address_enabled)
          {{ $customer->address }}, {{ $customer->barangay }}<br>
          {{ $customer->city }}, {{ $customer->province }} <br>
          @if ($customer->country_name || $customer->country)
            {{ $customer->country_name ?? $customer->country->name }} <br>
          @endif
        @endif
        </div>
      </td>
  </tr>
</table>
