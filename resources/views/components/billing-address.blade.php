<tr>
  <td style="padding: 0; text-align: left; color: #adb5bd;">
    @if ($subscription->is_billing_address_enabled)
      {{ $subscription->payor }}<br>
      {{ $subscription->billing_address }}<br>
      {{ $subscription->billing_barangay ? "$subscription->billing_barangay," : '' }},
      {{ $subscription->billing_city }},
      {{ $subscription->billing_province }} {{ $subscription->billing_zip_code }}<br>
      {{ $subscription->billing_country }}
    @else
      {{ $subscription->customer->name }}<br>
      {{ $subscription->customer->mobile_number }}<br>
      {{ $subscription->customer->email }}<br>

      @if (!$subscription->merchant->has_shippable_products && are_shippable_products($subscription->products))
        {{ $subscription->customer->country_name }}<br>
        {{ $subscription->customer->province }}
      @elseif ($subscription->merchant->has_shippable_products)
        {{ $subscription->customer->province }}
      @else
       {{ $subscription->customer->country_name }}
      @endif
    @endif
  </td>
</tr>

