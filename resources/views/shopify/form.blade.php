<!DOCTYPE html>
<html>
    <body class="antialiased">
      <form id='autosubmit' action="{{ $url }}" method="post">
          @foreach($cartData as $key => $cart)
            <input type="hidden" name="{{ $key }}" value="{{ is_array($cart) ? json_encode($cart) : $cart }}">
          @endforeach
      </form>

      <script type='text/javascript'>
        document.addEventListener("DOMContentLoaded", function(event) {
            document.createElement('form').submit.call(document.getElementById('autosubmit'));
        });
		  </script>
    </body>
</html>
