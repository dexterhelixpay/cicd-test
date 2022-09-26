<table role="presentation"
  style="
  width: 100%;
  border-collapse: separate;
  border-spacing: 15px;
  background-color: white;
  border-radius: 5px;"
>
  <tr>
    <td style="max-height: 125px; max-width: 360px; padding: 0;">
      @if ($merchant->marketing_card_image_url)
        <a href="{{ $merchant->marketing_card_image_url }}" target="_blank" style="display: block; position: relative;">
          <img style="border-radius: 5px" width="100%"
            src="{{ $merchant->marketing_card_image_path }}"
          />
        </a>
      @else
      <img style="border-radius: 5px" width="100%"
        src="{{ $merchant->marketing_card_image_path }}"
      />
      @endif
    </td>
  </tr>
</table>
