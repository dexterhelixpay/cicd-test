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
    @if ($transactionId)
      <tr>
        <td style="
          padding: 0;
          text-align: left;
          font-weight: bold;
          font-size: 16px;">
          @if ($isUnpaidOrFailedInitialOrder || $isInitialAndEditReminder)
            {{ $billingDate }} - Transaction #{{ $transactionId }}
          @else
            Transaction Details
            <div style="
              font-size: 14px !important;
              margin-top: 16px;
              margin-bottom: 0px;
            ">
              Billing Date - {{ $billingDate }} <br>
              <span style="font-weight: normal; color: #adb5bd; padding: 0">
                #{{ $transactionId }}
              </span>
            </div>
          @endif
        </td>
        @if (
          $hasEditButton
          && $merchant->is_subscriptions_editable
          && !$isUnpaidOrFailedInitialOrder
        )
          <td style="padding: 0; text-align: right; vertical-align: top;">
            <a href="{{$editProductUrl}}&action=edit" target="_blank" style="
                          text-decoration: none;
                          color: black !important;">
              EDIT
            </a>
          </td>
        @endif
      </tr>
    @endif

    @if (!$isUnpaidOrFailedInitialOrder && !$isInitialAndEditReminder)
      <tr>
        <td style="padding: 0;" colspan="2">
          <hr style="border-top: 1px solid #d7dce0" />
        </td>
      </tr>
    @endif

    @forelse ($orderedProducts as $product)
      <tr style="margin-top: 32px;">
        <td style="padding: 0; text-align: left;">
          <b>{{ $product->title }}<b>&nbsp;<b style="color:{{ $merchant->highlight_color }}">x{{ $product->quantity }}<b><br>
          <div style="color: #adb5bd; font-weight:lighter; font-size: 14px;">{{ formatRecurrenceText($product->payment_schedule['frequency'],$merchant) }}</div>
          @if ($product->option_values)
            @foreach ( $product->option_values as $option => $value)
              @if ($option !== 'Recurrences' && $option !== 'Frequency' && $option !== 'Title')
                <div style="color: #adb5bd; font-weight:lighter; font-size: 14px;">{{ $option }}: {{ $value }}</div>
              @endif
            @endforeach
          @endif
          @if ($product->product_properties)
            @foreach ( $product->product_properties as $property)
                <div style="
                    color: #adb5bd;
                    font-weight:lighter;
                    font-size: 14px;
                  "
                >
                  {{ $property['title'] }}: {{ strlen($property['value']) > 200 ? substr($property['value'], 0, 200).'...' : $property['value'] }}
                </div>
            @endforeach
          @endif

          @if ($product->sku_meta_notes)
            @foreach ( $product->sku_meta_notes as $option => $value)
              <div style="color: #adb5bd; font-weight:lighter; font-size: 14px;">{{ $value }}</div>
            @endforeach
          @endif
        </td>

        <td style="padding:0; text-align:right; float:right; display:flex; gap:5px;" rowspan="2">
          <b>₱{{ $product->price }}<b>
        </td>
      </tr>
    @empty
    @endforelse

    @if (!$isUnpaidOrFailedInitialOrder && !$isInitialAndEditReminder)
    <tr>
        <td style="padding: 0;" colspan="2">
          <hr style="border-top: 1px solid #d7dce0" />
        </td>
      </tr>

      @foreach ($priceBreakdown as $key => $breakdown)
        @if ($key == 'shipping_fee' || $breakdown['original_amount'] != 0 || $key == 'total_amount')
          <tr>
            <td style="
              padding: 0;
              text-align: left;
              font-weight: bold;
              {{ $key == 'total_amount' ? 'font-size: 16px;' : 'font-size:14px !important;' }}
              ">
              {{ $breakdown['label'] }}
            </td>
            <td style="
              padding:0;
              text-align:right;
              float:right;
              display:flex;
              gap:5px;
              {{ $key == 'total_amount' ? 'font-size: 16px;' : 'font-size:14px !important;' }}
              "
              rowspan="2"
            >
              @if ($key == 'total_amount' && $breakdown['original_amount'] == 0 )
                <b>FREE</b>
              @else
                <b>{{ $breakdown['amount'] }}<b>
              @endif
            </td>
          </tr>
        @endif
      @endforeach

     <tr>
        <td style="padding-top:15px" colspan="2">
          <a style="{{$buttonColor}}" href="{{ env('APP_URL') . "/v1/orders/{$order->id}/download_invoice" }}">Download Invoice</a>
        </td>
      </tr>

      <tr>
        <td style="padding: 0;" colspan="2">
          <hr style="border-top: 1px solid #d7dce0" />
        </td>
      </tr>

      {{ $slot }}
    @endif
  </tbody>
</table>
