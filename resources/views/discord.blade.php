<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    html, body {
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    body {
        background: #36393e;
        min-height: 100vh;
        min-width: 100vw;
        font-family: "Roboto Mono", "Liberation Mono", Consolas, monospace;
        color: rgba(255,255,255,.87);
    }

    .mx-auto {
        margin-left: auto;
        margin-right: auto;
    }

    .container,
    .container > .row,
    .container > .row > div {
        height: 100%;
    }

    img {
      width: 100px;
      margin-bottom: 20px;
    }

    .message {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
    }

    .text {
      font-weight: 300;
      text-align: center;
      margin-bottom: 8px;
    }

    .header {
      text-align:center;
      font-size: 60px;
      margin-bottom: 5px;
    }

    .footer {
      font-size:20px;
      text-align: center;
      font-weight: bold;
    }

    @media only screen and (max-width: 768px) {
      .header {
        font-size: 25px;
      }

      .footer {
        font-size: 18px;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="row">
        <div class="xs-12 md-6 mx-auto">
            <div class="message">
                <img src="{{ $imagePath }}">
                <div class="header">We'll be right back!</div>
                <div class="text">Our service is temporarily unavailble. We are currently working to restore it.</div>
                <div class="footer">Please try again later.</div>
            </div>
        </div>
    </div>
  </div>
</body>

</html>
