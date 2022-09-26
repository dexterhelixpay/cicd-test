    <tr>
        <td style="padding: 0; text-align: left; font-size: 14px; color: #adb5bd;">
          Payment Method
        </td>
    </tr>
    <tr>
        <td style="padding: 0; text-align: left; font-size: 14px;">
            <div style="display: inline-block; align: center; vertical-align: middle;" >
                <img
                    style="height: 40px; border-radius: 5px"
                    src="{{ $paymentMethodImagePath }}"
                />
              </div>
              <div style="display: inline-block; vertical-align: middle; margin-left: 10px; margin-right: 10px;">
                @if ($paymentType)
                  @if ($paymentType->id == 3 && $order->paymaya_masked_pan)
                    {{ $order->paymaya_masked_pan }}
                  @elseif(
                    $paymentType->id == 6
                    && $subscription->paymaya_wallet_mobile_number
                    && $subscription->paymaya_wallet_customer_name
                  )
                      {{ abbreviateName($subscription->paymaya_wallet_customer_name) }}<br>
                      {{ $subscription->paymaya_wallet_mobile_number }}
                  @endif

                  @if ($paymentType->id == 3 && !$order->paymaya_masked_pan)
                      Please set payment info
                  @endif
                @else
                  No selected payment method
                @endif
              </div>
              <!-- ORDER STATUS FAILED == 3-->
              @if ($order->order_status_id == 3)
                <div style="display: inline-block; vertical-align: middle;">
                  <div
                    style="
                      display: inline-block;
                      padding: 1em;
                      font-size: 75%;
                      font-weight: normal;
                      line-height: 1;
                      text-align: center;
                      white-space: nowrap;
                      vertical-align: baseline;
                      border-radius: 0.5rem;
                      color: #fff;
                      background-color: #dc3545;
                    "
                  >
                    &#x26A0; Failed
                  </div>
                </div>
              @endif

        </td>
        @if ($showChangeButton)
            <td style="padding: 0; text-align: right; vertical-align: top;">
                <button style="background-color:red;padding: 8px 20px; border-radius:5px; color: white !important; border-color: transparent">
                    <a href="{{$editUrl}}&action=edit#changePayment" target="_blank" style="color: white !important; text-decoration:none">Change</a>
                </button>
            </td>
        @else
          @if ($hasEditButton)
            <td style="padding: 0; text-align: right; vertical-align: top;">
                <a href="{{$editUrl}}&action=edit" target="_blank" style="color: black !important; text-decoration:none">EDIT</a>
            </td>
          @endif
        @endif
    </tr>
