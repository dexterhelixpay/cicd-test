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
      <td colspan="2" style="
        padding: 0;
        text-align: left;
        font-weight: bold;
        font-size: 16px;"
      >
        {{ $slot }}
      </td>
    </tr>

  @if ($slot->isNotEmpty())
    <tr>
      <td style="padding: 0;" colspan="2">
        <hr style="border-top: 1px solid #d7dce0" />
      </td>
    </tr>
  @endif

  @if ($subscriptionStatus != 'Cancelled')
    @if (
      ($merchant->are_orders_skippable && $merchant->has_shippable_products)
      && !are_all_single_recurrence($subscription->products)
      && $isWithNextOrder
    )
      <tr>
        <td style="padding: 0;" colspan="2">
          {{
            in_array($type, ['success','skipped','cancelled','shipped','confirmed'])
              ? 'Want to skip order?'
              : 'Want to skip this order?'
          }}
          <a href="{{ $skipUrl }}"
            style="font-weight: bold; text-decoration: none; color: black !important"
          >Skip</a>
        </td>
      </tr>
    @endif

    @if (
      $merchant->are_orders_cancellable && $hasEditButton
      && !are_all_single_recurrence($subscription->products)
      && $isWithNextOrder
      && !$order->hasPaymentLapsed()
    )
      <tr>
        <td style="padding: 0" colspan="2">
          <span>
            Want to cancel your {{ $merchant->subscription_term_singular }}?
          </span>

          <a
            href="{{ $cancelUrl }}"
            style="font-weight: bold; text-decoration: none; color: black !important"
          >
            Cancel
          </a>
        </td>
      </tr>
    @endif
  @endif

  @if ($contactUs)
    <tr>
      <td style="padding: 0;" colspan="2">
        @if ($contactType == 'url')
          Need Help?
          <a href="{{ $contactUs }}"
            style="font-weight: bold; text-decoration: none; color: black !important"
          >Contact Us</a>
        @else
          For any questions, please send an email to
          <span
            style="font-weight: bold; text-decoration: none; color: black !important"
          >{{ $contactUs }}</span>
        @endif
      </td>
    </tr>
  @endif
</table>
