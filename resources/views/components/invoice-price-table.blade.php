<table
  role="presentation"
  style="
  width: 100%;
  border-collapse: collapse;
  border-spacing: 15px;"
>
  <thead style="
    border-bottom: 1px solid #111111;
    font-size: 20px;
    line-height: 3;"
  >
    <tr>
      <th style="
        text-align: left;
        padding: 20px 10px;"
        width="35%"
      >
        DESCRIPTION
      </th>
      <th style="
        text-align: left;
        padding: 20px;"
        width="25%"
      >
        PRICE
      </th>
      <th style="
        text-align: center;
        padding: 20px;"
        width="20%"
      >
        QTY
      </th>
      <th style="
        text-align: right;
        border-bottom: 1px solid #111111;
        padding: 20px 10px;"
        width="25%"
      >
        AMOUNT
      </th>
    </tr>
  </thead>
  <tbody style="font-size: 16px;">
    @foreach ($products as $product)
      <tr style="
        color: #4f4f4f;"
      >
        <td style="
          text-align: left;
          padding: 20px 10px;"
        >
          <div style="
            text-transform: uppercase;
            font-size: 20px;
            color: #4f4f4f;"
          >
            {{ $product->title }}
          </div>
          <div style="
            color: #afafaf;
            margin-top: 12px"
          >
            {{ optional($product->selectedVariant)->title }}
          </div>
          @if ($product->sku_meta_notes)
            @foreach ($product->sku_meta_notes as $note)
              <div style="
                  color: #afafaf;
                  margin-top: 5px"
                >
                  {{$note}}
              </div>
            @endforeach
          @endif

        </td>
        <td style="
          text-align: left;
          padding: 20px 10px;
          vertical-align: top;"
        >
          PHP {{ number_format($product->price, 2) }}
        </td>
        <td style="
          text-align: center;
          padding: 20px 10px;
          vertical-align: top;"
        >
          {{ $product->quantity }}
        </td>
        <td style="
          text-align: right;
          padding: 20px 10px;
          vertical-align: top;"
        >
          PHP {{ number_format($product->total_price,2) }}
        </td>
      </tr>
    @endforeach
    <tr style="color: #4f4f4f;">
      <td></td>
      <td></td>
      <td style="
        padding: 10px 20px;
        vertical-align: top;"
        width="40%"
      >
        SUBTOTAL
      </td>
      <td style="
        text-align: right;
        padding: 10px;
        vertical-align: top;"
        width="25%"
      >
        PHP {{ number_format($subtotal,2) }}
      </td>
    </tr>
    @if ($shippingFee)
      <tr style="color: #4f4f4f;">
        <td></td>
        <td></td>
        <td style="
          padding: 10px 20px;
          vertical-align: top;"
          width="40%"
        >
          SHIPPING
        </td>
        <td style="
          text-align: right;
          padding: 10px;
          vertical-align: top;"
          width="25%"
        >
          PHP {{ number_format($shippingFee,2) }}
        </td>
      </tr>
    @endif
    @if ($order->voucher_code)
      <tr style="color: #4f4f4f;">
        <td></td>
        <td></td>
        <td style="
          padding: 10px 20px;
          vertical-align: top;"
          width="40%"
        >
          VOUCHER
          <div style="
            color: #afafaf;
            font-size: 13px"
          >
            ({{ $order->voucher_code }})
          </div>
        </td>
        <td style="
          text-align: right;
          padding: 10px;
          vertical-align: top;"
          width="25%"
        >
          - PHP {{ number_format($voucherAmount,2) }}
        </td>
      </tr>
    @endif
    @if ($discount)
      <tr style="color: #4f4f4f;">
        <td></td>
        <td></td>
        <td style="
          padding: 10px 20px;
          vertical-align: top;"
          width="40%"
        >
          CARD DISCOUNT
        </td>
        <td style="
          text-align: right;
          padding: 10px;
          vertical-align: top;"
          width="25%"
        >
          - PHP {{ number_format($discount,2) }}
        </td>
      </tr>
    @endif
    @if ($convenienceFee)
      <tr style="color: #4f4f4f;">
        <td></td>
        <td></td>
        <td style="
          padding: 10px 20px;
          vertical-align: top;
          text-transform: uppercase"
          width="40%"
        >
          {{ $convenienceLabel }}
        </td>
        <td style="
          text-align: right;
          padding: 10px;
          vertical-align: top;"
          width="25%"
        >
          PHP {{ number_format($convenienceFee,2) }}
        </td>
      </tr>
    @endif
    @if ($vat)
      <tr style="color: #4f4f4f;">
        <td></td>
        <td></td>
        <td style="
          padding: 10px 20px;
          vertical-align: top;
          text-transform: uppercase"
          width="40%"
        >
          VAT (12%)
        </td>
        <td style="
          text-align: right;
          padding: 10px;
          vertical-align: top;"
          width="25%"
        >
          PHP {{ number_format($vat,2) }}
        </td>
      </tr>
    @endif
  </tbody>
  <tfoot style="
    font-size: 16px;
    border-top: 1px solid #111111;"
  >
    <tr style="color: #4f4f4f;">
      <td></td>
      <td></td>
      <td style="
        padding: 20px;
        vertical-align: top;"
        width="40%"
      >
        TOTAL
      </td>
      <td style="
        text-align: right;
        padding: 20px 10px;
        vertical-align: top;"
        width="25%"
      >
        @if ($totalPrice > 0)
          PHP {{ number_format($totalPrice,2) }}
        @else
          FREE
        @endif
      </td>
    </tr>
</tfoot>
</table>

<table
  role="presentation"
  style="
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  font-size: 15px
  "
>
<tr>
  <th width="35%"></th>
  <th width="10%"></th>
  <th style="
    text-align: left;
    vertical-align: bottom;
    padding-left: 5px;
    padding-bottom: 3px;
    "
    width="20%"
  >
    STATUS
  </th>
  <th style="text-align: right; font-size:14px; vertical-align: bottom;"
    width="25%"
  >
    @if ($order->paid_at)
      PAID VIA &nbsp;
      <img style="
      max-height: 100%;
      max-width: 100%;
      height: 30px;
      vertical-align: text-bottom;"
      src="{{ $paymentIcon }}">
    @else
      PAYMENT DUE
    @endif
  </th>
</tr>
</table>
