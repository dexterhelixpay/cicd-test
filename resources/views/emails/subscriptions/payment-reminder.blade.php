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
        <div style="
          margin-bottom: 10px;
          font-size: 16px;
          font-family: inherit;
          line-height: 1.5;
          font-weight: 700;
          color: {{ $merchant->on_background_color ?? 'black' }} !important"
        >
          {{ replace_placeholders($options['title'], $order) ?? '' }}
        </div>

        <div style="
          font-size: 16px;
          font-family: inherit;
          line-height: 1.5;
          font-weight: 400;
          margin-top: 2px;
          color: {{ $merchant->on_background_color ?? 'black' }} !important"
        >
          {{ replace_placeholders($options['subtitle'], $order) }}
        </div>
      </td>
    </tr>

    @if (($options['has_pay_button'] ?? true) && !data_get($options,'is_edit_reminder'))
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
          is-payment-reminder="{{ $options['has_pay_button'] ?? true }}"
          has-edit-button="true"
          :is-console-booking="$options['is_console_created_subscription'] ?? false"
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

    @if ($options['has_order_summary'])
     <tr>
        <td style="padding: 10px 20px;">
          <x-order-summary
            :merchant="$merchant"
            :subscription="$subscription"
            :order="$order"
            :is-console-booking="$options['is_console_created_subscription'] ?? false"
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

    @if ($merchant->marketing_card_image_path && $merchant->marketing_card_expires_at > now()->toDateTimeString())
      <tr>
        <td style="padding: 10px 20px;">
          <x-marketing :merchant="$merchant" />
        </td>
      </tr>
    @endif

    <tr>
      <td style="padding: 10px 20px;">
        <x-customer-details
          :subscription="$subscription"
          :order="$order"
          :type="$options['type']"
          is-payment-reminder="{{ $options['has_pay_button'] ?? true }}"
          has-edit-button="true"
          :is-console-booking="$options['is_console_created_subscription'] ?? false"
        >
          @if ($merchant->has_shippable_products)
              <x-shipping-address :subscription="$subscription" />
          @else
              <x-billing-address :subscription="$subscription" />
          @endif
        </x-shipping-address>
      </td>
    </tr>


    @if ($hasOtherInfo)
      <tr>
        <td style="padding: 10px 20px;">
            <x-other-information
              :subscription="$subscription"
              :order="$order"
              has-edit-button="true"
              :is-console-booking="$options['is_console_created_subscription'] ?? false"
            />
        </td>
      </tr>
    @endif

    <tr>
      <td style="padding: 10px 20px 20px 20px;">
        <x-subscription-status
          :subscription="$subscription"
          :order="$order"
          :title="$options['subscription_status_title'] ?? 'Please Pay now to '.start_or_continue($order->subscription, $order->id).' '.$merchant->subscription_term_singular"
          :merchant="$merchant"
          :order-id="$order->id"
          :subscription-id="$subscription->id"
          :subscription-status="$options['subscription_status'] ?? 'Payment Pending'"
          :subscription-status-color="$options['subscription_status_color'] ?? '#DAC400'"
          is-payment-reminder="true"
          has-edit-button="true"
          :is-console-booking="$options['is_console_created_subscription'] ?? false"
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
  </table>
</x-layouts.master>
