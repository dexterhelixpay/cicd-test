<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@shopify/polaris@6.6.0/dist/styles.css">

  <style>
    :root {
      --p-surface: white;
      --p-button-drop-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
      --p-border-radius-base: 0.4rem;
      --p-text: rgba(32, 34, 35, 1);
      --p-border-neutral-subdued: rgba(186, 191, 195, 1);
      --p-border-subdued: rgba(201, 204, 207, 1);
      --p-border-shadow-subdued: rgba(186, 191, 196, 1);
      --p-button-font-weight: 500;
      --p-action-secondary: rgba(255, 255, 255, 1);
      --p-action-secondary-disabled: rgba(255, 255, 255, 1);
      --p-action-secondary-hovered: rgba(246, 246, 247, 1);
      --p-action-secondary-pressed: rgba(241, 242, 243, 1);
      --p-action-secondary-depressed: rgba(109, 113, 117, 1);
    }

    body {
      background: black;
    }

    .card {
      background: white;
      border-radius: 0.4rem;
    }

    .container {
      margin: 0 auto;
      max-width: 650px;
      padding: 8px;
      min-height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    @media (min-width: 650px) {
      .container {
        padding: 16px;
      }
    }

    .snippet {
      background-color: #EEE;
      padding: 8px;
      border-radius: 3px;
      margin-bottom: 0;
      overflow: auto;
    }

    .logo {
      height: 50px;
    }

    .logo-container {
      text-align: center;
      margin-bottom: 8px;
    }

    .logo-svg {
      max-width: 200px;
      height: auto;
      margin-bottom: 8px;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="logo-container">
      <svg class="logo-svg" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="1000px" height="265px" viewBox="0 0 1000 265" enable-background="new 0 0 1000 265" xml:space="preserve">  <image id="image0" width="1000" height="265" x="0" y="0"
        href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAA+gAAAEJCAMAAAAw+v3YAAAABGdBTUEAALGPC/xhBQAAACBjSFJN
    AAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAA2FBMVEUAAAD/X4//X4//X4//
    X4//X4//X4//X4//X4//X4//X4//X4//X4//X4//X4//X4//////////////////////////////
    ///////////////////////////////3a5Wa/+Ga/+Ga/+Ga/+Ga/+Ga/+Ga/+Ga/+Ga/+Ga/+Ga
    /+Ga/+Ga/+Ga/+Ga/+EnpOFHrNtEu+EnpOEnpOEnpOEnpOEnpOEnpOEnpOEnpOEnpOEnpOEnpOEn
    pOEnpOEnpOH/X4/////yc5nmh6TNr7ia/+FEu+EnpOFWZtBmAAAAQHRSTlMAML+AUEDPj2Ag33AQ
    n++vQCCAEGCPv8+fUDDvcK/fz7+AYEDfr3Awj1DPEO8gnxDfgJ+AcEAwv9+v7yDPj1BgoKQn4AAA
    AAFiS0dEEJWyDSwAAAAHdElNRQflCxgFDTT68SbjAAAWRklEQVR42u2d63obx5FAGcmWFTmiByQF
    8SZG9ipeb6Iku46zUrS+QMli+f5vtAJFiujqGaC7qvpi4Jw//j5Tc+lunOma6ssc/OZBaR4+/Ow3
    BwDQks8XVXj0xePfti4qwP5SSfSPstOzA7ShougfePLl71oXGGAfqSv6B57SrQNUp7roi8WDz1sX
    GmDfaCD6B9VJzAFUpYnoi8UXvKsDVKSR6IsnxO8A9WglOp06QEXaib54RP4doBINRV8cPm5deoA9
    oaXoi8XD1sUH2A/air542rr8AHtBY9ExHaAGrUXHdIAKNBcd0wHK0170xRet6wBg5+lA9AWjbACF
    6UH0BTNnAMrSheiHzIYFKEoXoi8etK4GgN2mD9EXX7auB4CdphPRD9mKAqAgnYhO8A5Qkl5EX3zW
    uiYAdphuRH/SuiYAdphuRCcfB1COfkRnMB2gGP2ITpcOUIyOROctHaAUHYnO4haAUvQkOmPpAIXo
    SfQF0+MAytCV6KTjAMrQleiPWtcGwI7SlejE7gBl6Et0YneAIvQl+letqwNgN+lL9MPW1QGwm/Ql
    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
    ZWFkeXHJZTwAAAAASUVORK5CYII=" />
    </svg>
    </div>
    <div class="Polaris-Card card">
      <div class="Polaris-Card__Header">
        <h2 class="Polaris-Heading">Theme Integration</h2>
      </div>
      <div class="Polaris-Card__Section">
        <p>Refer to this link below for the shopify theme integration guide:</p>
        <a href="https://helixpay.readme.io/docs/shopify-theme-integration" target="_blank">https://helixpay.readme.io/docs/shopify-theme-integration</a>
      </div>
    </div>
  </div>
</body>

</html>
