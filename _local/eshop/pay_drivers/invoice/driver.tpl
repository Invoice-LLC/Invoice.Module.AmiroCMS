%%include_language "_local/eshop/pay_drivers/invoice/driver.lng"%%

<!--#set var="settings_form" value="
  <tr>
    <td>%%api_key%%:</td>
    <td><input type="text" name="api_key" class="field" value="##api_key##" size="40"></td>
  </tr>
  <tr>
    <td>%%login%%:</td>
    <td><input type="text" name="login" class="field" value="##login##" size="40"></td>
  </tr>
  <tr>
"-->

<!--#set var="checkout_form" value="
    <form name="paymentformpayanyway" action="##payment_url##" method="POST">
    <input type="submit" name="sbmt" class="btn" value="      %%button_caption%%      " ##disabled##>
    </form>
     <script>document.paymentformpayanyway.submit();</script>
"-->

<!--#set var="pay_form" value="
    <form name="payment" method="post" action="##payment_url##" accept-charset="UTF-8">
    </form>
    <script>document.payment.submit();</script>
"-->
