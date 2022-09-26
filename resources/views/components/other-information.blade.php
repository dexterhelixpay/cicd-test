<table role="presentation"
  style="
  width: 100%;
  border-collapse: separate;
  border-spacing: 15px;
  background-color: white;
  border-radius: 5px;"
>
  <tr>
    <td style="
      padding: 0;
      text-align: left;
      font-weight: bold;
      font-size: 16px;"
    >
      Other Information
    </td>
    @if ($hasEditButton)
      <td style="padding: 0; text-align: right;">
        <a
          href="{{$editUrl}}&action=edit"
          target="_blank"
          style="
            text-decoration: none;
            color: black !important;"
        >
          EDIT
        </a>
      </td>
    @endif
  </tr>

  <tr>
    <td style="padding: 0; text-align: left; color: #adb5bd;" colspan="2">
      @foreach ($customFields as $field)
        @if (isset($field['value']))
          <div>
            <span>{{ $field['label'] }}: </span>
            <span style="margin-left: 10px; font-weight: bold">{{ $field['value'] }}</span>
          </div>
        @endif
      @endforeach
    </td>
  </tr>
</table>

