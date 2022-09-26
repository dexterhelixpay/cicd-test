<div
    style="
      color: rgb(0, 0, 0);
      margin: auto;
      display: flex;
      flex-direction: row;"
>
    <div style="margin-bottom: 5px; padding: 5px 10px;">
      <div style="width: 100%; padding: 0px;">
          <img
            width="30"
            src="{{ $icon }}"
            alt="Helixpay Icon"
            style="
             {{ $backgroundColor }}
              border-radius: 50%;
              padding: 0px;"
          />
      </div>
    </div>
      <div style="padding: 5px 0px 0px">
          <div
              style="
                padding: 0px 20px;
                font-size: 16px;
                font-family: inherit;
                line-height: 1.5;
                margin-bottom: 5px;
                font-weight: 700;
                color: {{ $merchant->on_background_color ?? 'black' }} !important"
          >
            {{ $header }}
          </div>
          <div
              style="
                  padding: 0px 20px;
                  color: #adb5bd;
                  overflow-wrap: break-word;
                  letter-spacing: normal;
                  padding: 0px 20px;
                  font-size: 14px;
                  font-family: inherit;
              "
          >
            {{ $subheader }}
          </div>
    </div>
</div>
