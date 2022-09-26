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
        text-align: left;
        font-weight: bold;
        font-size: 16px;">
        Order Summary
      </td>
    </tr>

    @foreach ($priceBreakdown as $key => $breakdown)
    @if ($key == 'shipping_fee' || $breakdown['original_amount'] != 0 || $key == 'shipping_fee')
      <tr>
        <td style="
          padding: 0;
          text-align: left;
          {{ $key == 'total_amount' ? 'padding-left: 0;' : 'padding-left: 10px;' }}
          {{ $key == 'total_amount' ? 'font-weight: bold;' : 'font-weight: normal;' }}
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
          {{ $key == 'total_amount' ? 'font-weight: bold;' : 'font-weight: normal !important;' }}
          {{ $key == 'total_amount' ? 'font-size: 16px;' : 'font-size:14px !important;' }}
          "
          rowspan="2"
        >
          @if ($key == 'total_amount' && $breakdown['original_amount'] == 0)
            FREE
          @else
            {{ $breakdown['amount'] }}
          @endif
        </td>
      </tr>
    @endif
  @endforeach

    {{ $slot }}
  </tbody>
</table>
