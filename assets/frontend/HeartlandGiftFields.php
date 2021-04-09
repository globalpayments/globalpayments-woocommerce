<?php

defined( 'ABSPATH' ) || exit;

?>

<fieldset>
      <div class="securesubmit-content gift-card-content">
            <div class="form-row form-row-wide" id="gift-card-row">
                  <label id="gift-card-label" for="gift-card-number"><?php _e('Use a gift card', 'wc_securesubmit'); ?></label>
                  <div id="gift-card-input">
                        <input type="tel" placeholder="Gift card" id="gift-card-number" value="" class="input-text">
                        <input type="tel" placeholder="PIN" id="gift-card-pin" value="" class="input-text">
                        <p id="gift-card-error"></p>
                        <p id="gift-card-success"></p>
                  </div>
                  <button id="apply-gift-card" class="button"><?php _e('Apply', 'wc_securesubmit'); ?></button>
                  
<?php
      $html = '<script data-cfasync="false" type="text/javascript">';
      $html .= 'if( typeof ajaxurl === "undefined") { ';
      $html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
      $html .= '}';
      $html .= '</script>';

      echo $html;
?>

<script data-cfasync="false">

      jQuery("#apply-gift-card").on('click', function (event) {
            event.preventDefault();
            applyGiftCard();
      });

      function applyGiftCard () {
            var httpRequest = new XMLHttpRequest();

            var gift_card_number = document.getElementById('gift-card-number').value;
            var gift_card_pin = document.getElementById('gift-card-pin').value;

            var post_string = 'action=use_gift_card&gift_card_number=' + gift_card_number + '&gift_card_pin=' + gift_card_pin;

            httpRequest.open('POST', ajaxurl, false);
            httpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            httpRequest.send(post_string);

            jQuery('body').trigger('update_checkout');
      };

      jQuery(document).on( 'click', '.securesubmit-remove-gift-card', function (event) {
            event.preventDefault();

            var removedCardID = jQuery(this).attr('id');

            jQuery.ajax({
                  url: ajaxurl,
                  type: "POST",
                  data: {
                        action: 'remove_gift_card',
                        securesubmit_card_id: removedCardID
                  }
            }).done(function () {
                  jQuery('body').trigger('update_checkout');
                  jQuery(".button[name='update_cart']")
                        .prop("disabled", false)
                        .trigger("click");
            });
      });

</script>
            </div>
            <div class="clear"></div>
      </div>
</fieldset>
