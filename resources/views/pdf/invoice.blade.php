<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title></title>
    <style>
        @page { margin: 120px 25px 150px; }
        @font-face {
          font-family: 'Lato Regular', sans-serif;
          src: url( {{ storage_path('fonts/Lato-Regular.ttf') }}) format("truetype");
          font-weight: 400;
          font-style: normal;
        }
        table, td, div, h1, p {
          font-family: 'Lato Regular', sans-serif;
          font-weight: normal;
          font-style: normal;
          letter-spacing: 1.5px;
        }
        .invoice-header { position: fixed; top: -100px; left: 0px; right: 0px; }
        .invoice-footer { position: fixed; bottom: -90px; left: 0px; right: 0px; text-transform: uppercase; z-index: -10; max-height: 50px;}
        .corporate-info p { margin: 0 !important; max-height: 180px;}
   </style>
</head>

<body style="margin: 0; padding: 0;">

    <div
      class="invoice-header"
      style="
      background-color: #ffffff!important;
      width: 100%;
      max-width: 768px;
      text-align: center;
      margin: 0 auto;"
    >
      <img
        style="max-height: 100px !important; max-width: 100%;"
        src="{{ $merchant->logo_image_path }}"
      >
    </div>
    <div
      class="invoice-footer"
      style="
      background-color: #ffffff!important;
      width: 100%;
      max-width: 768px;
      max-height: 500px
      margin: 0 auto;"
    >
      <x-invoice-footer
        :merchant="$merchant"
      />
    </div>

  <main>
    <div style="padding: 0px 20px;">
      <x-invoice-details
        :order="$order"
        :subscription="$subscription"
        :customer="$customer"
      />
    </div>

    <div style="padding: 10px 20px;">
      <x-invoice-price-table
        :order="$order"
        :subscription="$subscription"
        :merchant="$merchant"
      />
    </div>
  </main>
</body>

</html>
