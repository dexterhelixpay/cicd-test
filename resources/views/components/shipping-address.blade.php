<tr>
  <td style="padding: 0; text-align: left; color: #adb5bd;">
    @if ($subscription->merchant->is_address_enabled)
      {{ $subscription->recipient }}<br>
      {{ $subscription->shipping_address }}<br>
      {{ $subscription->shipping_barangay ? "$subscription->shipping_barangay," : '' }}
      {{ $subscription->shipping_city }},
      {{ $subscription->shipping_province }} <br>
      {{ $subscription->shipping_country }} {{ $subscription->shipping_zip_code }}
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
