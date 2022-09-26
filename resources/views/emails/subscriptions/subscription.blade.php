<x-layouts.master :is-test-email="data_get($options,'is_test_email')">
  <table
    role="presentation"
    style="width: 100%; border-collapse: collapse; {{ $bgColor }}"
  >
    <x-header-banner
      :bg-color="$headerBgColor"
      :image-path="$merchant->logo_image_path"
    />

    <tr>
      <td style="padding: 20px 20px 10px 20px;">
      @if(
        in_array($options['type'], [
          'failed',
          'success',
          'shipped',
          'skipped',
          'cancelled',
          'edit-confirmation',
          'payment',
          'confirmed'
        ])
      )
        <div style="
          margin-bottom: 10px;
          font-size: 16px;
          font-family: inherit;
          line-height: 1.5;
          font-weight: 700;
          color: {{ $merchant->on_background_color ?? 'black' }} !important
        ">
          {{ replace_placeholders($options['title'], $order) ?? '' }}
        </div>
        <div style="
          font-size: 16px;
          font-family: inherit;
          line-height: 1.5;
          font-weight: 400;
          margin-top: 2px;
          color: {{ $merchant->on_background_color ?? 'black' }} !important
        ">
          {!! replace_placeholders($options['subtitle'], $order) ?? '' !!}
        </div>

        @if (data_get($options,'banner_image_path', null))
          <img style="border-radius: 5px; margin-top:15px" width="100%"
            src="{{ data_get($options,'banner_image_path') }}"
          />
        @endif

        {{-- @if (in_array($options['type'], ['success', 'confirmed']))
          <div style="font-size: 16px; line-height: 20px; font-weight: 400; margin-top: 10px; color: {{ $merchant->on_background_color ?? 'black' }} !important">
              You can view your {{ are_all_single_recurrence($subscription->products) ? 'order' : $merchant->subscription_term_singular }} details anytime on your <a style="text-decoration: none !important; font-weight: 700; color: {{ $merchant->on_background_color ?? 'black' }} !important" href="{{ $customerProfileUrl }}" target="_blank">customer profile page</a>.
          </div>
        @endif
      @elseif($options['is_custom_merchant'])
        <div style="margin-bottom: 10px; font-size: 16px; line-height: 20px; font-weight: 700; color: {{ $merchant->on_background_color ?? 'black' }} !important">
           Start {{ $merchant->subscription_term_singular }} today with {{ $merchant->name }}
        </div>
        <div style="font-size: 16px; line-height: 20px; font-weight: 400; margin-top: 2px; color: {{ $merchant->on_background_color ?? 'black' }} !important">
            @if ($merchant->id == data_get(setting('CustomMerchants', []), 'yardstick', null))
                Pay now and benefit from easy recurring payments. We automatically charge you each month to continue your {{ $merchant->subscription_term_singular }}.
            @else
                Set your payment method for easy automated recurring payments.
            @endif
        </div> --}}
      @endif
      </td>
    </tr>
    @if (
      (isset($options['is_console_created_subscription']) ||
      $options['has_pay_button']) &&
      in_array($options['type'], ['payment','failed'])
    )
      <tr>
        <td style="padding: 10px 20px;">
          <x-pay-button>
            <x-slot name="instruction">
              <x-pay-instruction
                :subscription="$subscription"
                :order="$order"
                :background-color="$buttonColor"
                :header="$options['payment_headline']"
                :subheader="$options['payment_instructions']"
              />
            </x-slot>

            <a href="{{$payButtonLink ?? $editUrl}}&action=payNow"
              target="_blank"
              style="
                text-align: center;
                font-weight: bold;
                font-size: 16px;
                color: white !important;
                text-decoration:none !important;
                {{ $buttonColor }}
                padding: 14px 28px;
                display: block;
                cursor: pointer;
                margin-top: 10px;
                margin-bottom: 10px;
                border-radius: 5px;"
            >
              {{ $payButtonText ?? 'Pay Now' }}
            </a>
          </x-pay-button>
        </td>
      </tr>
    @endif

    @if (in_array($options['type'], ['success', 'confirmed']))
      @if ($hasDiscordButton)
        <tr>
          <td style="padding: 10px 20px;">
            <a href="{{$discordLink}}"
              target="_blank"
              style="
                text-decoration:none !important;
                display: block;
                cursor: pointer;"
            >
              <x-discord :merchant="$merchant"/>
            </a>
          </td>
        </tr>
      @endif
    @endif

    <tr>
      <td style="padding: 10px 20px;">
        <x-selection
          transaction-id="{{ formatId($order->created_at, $order->id) }}"
          :product="$product"
          :order="$order"
          :merchant="$merchant"
          :subscription="$subscription"
          :type="$options['type']"
          :total-amount-label="$options['total_amount_label']"
          :has-edit-button="$options['has_edit_button']"
          :is-console-booking="$options['is_console_created_subscription']"
        >
          <x-next-payment
            subscription-status="Payment Pending"
            :order="$order"
            is-payment-reminder="{{ $options['has_pay_button'] ?? true }}"
            has-edit-button="true"
          />
        </x-selection>
      </td>
    </tr>

    @if (in_array($options['type'], ['success', 'confirmed']))
      <tr>
        <td style="padding: 10px 20px;">
          <a href="{{$viberSubscriptionLink}}"
            target="_blank"
            style="
              text-decoration:none !important;
              display: block;
              cursor: pointer;"
          >
            <x-viber-notification :merchant="$merchant"/>
          </a>
        </td>
      </tr>
    @endif

    @if ($options['has_order_summary'])
     <tr>
        <td style="padding: 10px 20px;">
          <x-order-summary
            :merchant="$merchant"
            :subscription="$subscription"
            :order="$order"
            :total-amount-label="$options['total_amount_label']"
            :type="$options['type']"
            :is-console-booking="$options['is_console_created_subscription']"
          >
            <x-next-payment
              subscription-status="Payment Pending"
              :order="$order"
              is-payment-reminder="{{ $options['has_pay_button'] ?? true }}"
              has-edit-button="true"
            />
          </x-order-summary>
        </td>
      </tr>
    @endif

    @if ($options['has_subscription_convertion_component'])
      {{-- <tr>
        <td style="padding: 10px 20px;">
          <x-subscription-convertion
            :merchant="$merchant"
            :order-id="$order->id"
            :subscription-id="$subscription->id"
          />
        </td>
      </tr> --}}
    @endif

    @if ($options['type'] == 'success' || $options['type'] == 'confirmed')
      @if ($merchant->marketing_card_image_path && $merchant->marketing_card_expires_at > now()->toDateTimeString())
        <tr>
          <td style="padding: 10px 20px;">
            <x-marketing :merchant="$merchant" />
          </td>
        </tr>
      @endif
    @endif

    <tr>
      <td style="padding: 10px 20px;">
        <x-customer-details
          :subscription="$subscription"
          :order="$order"
          :type="$options['type']"
          :is-console-booking="$options['is_console_created_subscription'] ?? false"
          :has-edit-button="$options['has_edit_button']"
          :has-pay-button="$options['has_pay_button'] ?? true"
        >
          @if (are_shippable_products($subscription->products))
              <x-shipping-address :subscription="$subscription" />
          @else
              <x-billing-address :subscription="$subscription" />
          @endif
        </x-customer-details>
      </td>
    </tr>

    @if ($subscription->hasOtherInfo())
      <tr>
        <td style="padding: 10px 20px;">
            <x-other-information
              :subscription="$subscription"
              :order="$order"
              :has-edit-button="$options['has_edit_button']"
              :type="$options['type']"
              :is-console-booking="$options['is_console_created_subscription']"
            />
        </td>
      </tr>
    @endif

@unless ($options['type'] == 'shipped')
    <tr>
      <td style="padding: 10px 20px;">
        <x-subscription-status
          :subscription="$subscription"
          :order="$order"
          :title="$options['title']"
          :merchant="$merchant"
          :order-id="$order->id"
          :subscription-id="$subscription->id"
          :subscription-status="$options['status']['label']"
          :subscription-status-color="$options['status']['color']"
          :has-edit-button="$options['has_edit_button']"
          :type="$options['type']"
          :is-console-booking="$options['is_console_created_subscription']"
        >
          @if ($options['payment_instructions_headline'])
            <x-pay-instruction
              :subscription="$subscription"
              :order="$order"
              :background-color="$buttonColor"
              :header="$options['payment_instructions_headline']"
              :subheader="$options['payment_instructions_subheader']"
            />
          @endif
        </x-subscription-status>
      </td>
    </tr>
@endunless
  </table>
</x-layouts.master>
