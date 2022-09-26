<table
  role="presentation"
  style="
  width: 100%;
  border-spacing: 15px;
  background-color: white;
  border-radius: 5px;"
>
  <tr>
    <td>
      <div
        style="
          color: rgb(0, 0, 0);
          margin: auto;
          display: flex;
          flex-direction: row;"
      >
          <div style="margin-bottom: 5px; padding: 5px;">
            <div style="padding: 0px;">
                <img
                  width="40"
                  src="{{ $viberImage }}"
                  style="
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
                      font-weight: 700;"
                >
                  Subscription Updates on Viber
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
                  Receive {{$merchant->name}} notifications
                </div>
          </div>
      </div>
    </td>
  </tr>
</table>
