<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards;

use stdClass;
use Exception;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;


defined('ABSPATH') || exit;

class HeartlandGiftGateway
{
    function __construct($heartlandGateway = null)
    {
        if(is_null($heartlandGateway)){
            $heartlandGateway = new HeartlandGateway();
        }
        $this->secret_api_key = $heartlandGateway->get_backend_gateway_options()['secretApiKey'];
    }

    protected $temp_balance;
    protected $gift_card_pin_submitted;

    protected function configureServiceContainer()
    {
        $config = new PorticoConfig();
        $config->secretApiKey = $this->secret_api_key;
        $config->developerId = "002914";
        $config->versionNumber = "1510";

        ServicesContainer::configureService($config);
    }

    public function applyGiftCard()
    {
        $gift_card_balance = $this->giftCardBalance(
            wc_clean( $_POST['gift_card_number'] ),
            wc_clean( $_POST['gift_card_pin'] )
        );

        if ($gift_card_balance['error']) {
            echo json_encode(
                array(
                    'error' => 1,
                    'message' => $gift_card_balance['message'],
                )
            );
        } else {
            $this->temp_balance = $gift_card_balance['message'];

            $this->addGiftCardToCartSession();
            $this->updateGiftCardCartTotal();

            echo json_encode(
                array(
                    'error'   => 0,
                    'balance' => html_entity_decode(get_woocommerce_currency_symbol()) . $gift_card_balance['message'],
                )
            );
        }

        wp_die();
    }

    public function giftCardBalance($gift_card_number, $gift_card_pin)
    {
        $this->configureServiceContainer();

        if (empty($gift_card_pin)) {
            return array(
                'error'   => true,
                'message' => "PINs are required. Please enter a PIN and click apply again.",
            );
        }

        $this->gift_card = $this->giftCardObject($gift_card_number, $gift_card_pin);

        try {
            $response = $this->gift_card->balanceInquiry()
                ->execute();
        } catch (Exception $e) {
            return array(
                'error'   => true,
                'message' => "The gift card number you entered is either incorrect or not yet activated.",
            );
        }

        return array(
            'error' => false,
            'message' => $response->balanceAmount,
        );
    }

    protected function giftCardObject($gift_card_number, $gift_card_pin)
    {
        $gift_card         = new HeartlandGiftCard();
        $gift_card->number = $gift_card_number;
        $gift_card->pin    = $gift_card_pin;

        return $gift_card;
    }

    protected function addGiftCardToCartSession()
    {
        $this->gift_card->gift_card_name = $this->giftCardName($this->gift_card->number);
        $this->gift_card->gift_card_id   = sanitize_title($this->gift_card->gift_card_name);
        $this->gift_card->temp_balance   = floatval($this->temp_balance);

        WC()->session->set('securesubmit_gift_card_object', $this->gift_card);
    }

    protected function giftCardName($gift_card_number)
    {
        $digits_to_display = 5;
        $last_digits       = substr($gift_card_number, $digits_to_display * - 1);

        return __('Gift Card', 'wc_securesubmit') . ' ' . $last_digits;
    }

    protected function updateGiftCardCartTotal()
    {
        $gift_card_object_entered = WC()->session->get('securesubmit_gift_card_object');
        if (is_null($gift_card_object_entered)) {
            $gift_card_object_entered = (object)array();
        }

        $gift_card_object_applied = WC()->session->get('heartland_gift_card_applied');
        if (is_null($gift_card_object_applied)) {
            $gift_card_object_applied = (object)array();
        }

        $original_total = $this->getOriginalCartTotal();

        $securesubmit_data = WC()->session->get('securesubmit_data');
        if (!is_object($securesubmit_data)) {
            $securesubmit_data = new stdClass();
        }

        $securesubmit_data->original_total = $original_total;
        WC()->session->set('securesubmit_data', $securesubmit_data);

        $this->updateGiftCardTotals();

        if (is_object($gift_card_object_entered) && count(get_object_vars($gift_card_object_entered)) > 0) {
            if ($gift_card_object_entered->temp_balance === '0.00') {
                WC()->session->__unset('securesubmit_gift_card_object');

                $zero_balance_message = apply_filters(
                    'securesubmit_zero_balance_message',
                    sprintf(
                        __('%s has a balance of zero and could not be applied to this order.', 'wc_securesubmit'),
                        $gift_card_object_entered->gift_card_name
                    )
                );

                wc_add_notice($zero_balance_message, 'error');
            } else {
                if (!(is_object($gift_card_object_applied) && count(get_object_vars($gift_card_object_applied)) > 0)) {
                    $gift_card_object_applied = new stdClass;
                }

                $gift_card_object_entered->used_amount                               = $this->giftCardUsageAmount();
                $gift_card_object_applied->{$gift_card_object_entered->gift_card_id} = $gift_card_object_entered;

                WC()->session->set('heartland_gift_card_applied', $gift_card_object_applied);
                WC()->session->__unset('securesubmit_gift_card_object');
            }
        }

        return $gift_card_object_applied;
    }

    protected function getOriginalCartTotal()
    {
        $cart_totals = WC()->session->get('cart_totals');
        $original_total = round(
            array_sum(
                array(
                    (!empty($cart_totals['subtotal']) ? $cart_totals['subtotal'] : 0),
                    (!empty($cart_totals['subtotal_tax']) ? $cart_totals['subtotal_tax'] : 0),
                    (!empty($cart_totals['shipping_total']) ? $cart_totals['shipping_total'] : 0),
                    (!empty($cart_totals['shipping_tax']) ? $cart_totals['shipping_tax'] : 0),
                    (!empty($cart_totals['fee_total']) ? $cart_totals['fee_total'] : 0),
                    (!empty($cart_totals['fee_tax']) ? $cart_totals['fee_tax'] : 0),
                )
            ),
            2
        );
        return $original_total;
    }

    protected function updateGiftCardTotals()
    {
        $gift_cards_applied = WC()->session->get('heartland_gift_card_applied');

        $original_total = $this->getOriginalCartTotal();
        $remaining_total = $original_total;

        if (is_object($gift_cards_applied) && count(get_object_vars($gift_cards_applied)) > 0) {
            foreach ($gift_cards_applied as $gift_card) {
                $order_total_after_gift_card = $remaining_total - $gift_card->temp_balance;

                if ($order_total_after_gift_card > 0) {
                    $gift_card->used_amount = $gift_card->temp_balance;
                } else {
                    $gift_card->used_amount = $remaining_total;
                }

                $gift_cards_applied->{$gift_card->gift_card_id} = $gift_card;
                $remaining_total = $remaining_total - $gift_card->used_amount;
            }
        }

        WC()->session->set('heartland_gift_card_applied', $gift_cards_applied);
    }

    protected function giftCardUsageAmount($updated = false)
    {

        $cart_totals = WC()->session->get('cart_totals');
        $cart_total = round($cart_totals['total'], 2);
        $gift_card_object = WC()->session->get('securesubmit_gift_card_object');

        if (round($gift_card_object->temp_balance, 2) <= $cart_total) {
            $gift_card_applied_amount = $gift_card_object->temp_balance;
        } else {
            $gift_card_applied_amount = $cart_total;
        }

        return $gift_card_applied_amount;
    }

    public function addGiftCards()
    {
        // TODO: Add warnings and success messages
        // $gift_cards_allowed = $this->giftCardsAllowed();
        $gift_cards_allowed = true;

        // No gift cards if there are subscription products in the cart
        if ($gift_cards_allowed) {
            $original_total = $this->getOriginalCartTotal();
            $gift_card_object_applied = $this->updateGiftCardCartTotal();

            if (is_object($gift_card_object_applied) && count(get_object_vars($gift_card_object_applied)) > 0) {
                $securesubmit_data = WC()->session->get('securesubmit_data');
                $securesubmit_data->original_total = $original_total;
                WC()->session->set('securesubmit_data', $securesubmit_data);

                $message           = __('Total Before Gift Cards', 'wc_securesubmit');
                
                $ajaxUrl = admin_url('admin-ajax.php');
                $order_total_html  = <<<EOT
                <script data-cfasync="false" type="text/javascript">
                    if( typeof ajaxurl === "undefined") {
                        var ajaxurl = "{$ajaxUrl}";
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
                EOT;
                
                $order_total_html .= '<tr id="securesubmit_order_total" class="order-total">';
                $order_total_html .= '<th>' . $message . '</th>';
                $order_total_html .= '<td data-title="' . esc_attr($message) . '">' . wc_price($original_total) . '</td>';
                $order_total_html .= '</tr>';

                echo apply_filters('securesubmit_before_gift_cards_order_total', $order_total_html, $original_total, $message);

                foreach ($gift_card_object_applied as $applied_gift_card) {
                    $remove_link = '<a href="#" id="' . $applied_gift_card->gift_card_id . '" class="securesubmit-remove-gift-card">(Remove)</a>';

                    $gift_card_html  = '<tr class="fee">';
                    $gift_card_html .= '<th>' . $applied_gift_card->gift_card_name . ' ' . $remove_link . '</th>';
                    $gift_card_html .= '<td data-title="' . esc_attr($applied_gift_card->gift_card_name) . '">' . wc_price($applied_gift_card->used_amount) . '</td>';
                    $gift_card_html .= '</tr>';

                    echo apply_filters('securesubmit_gift_card_used_total', $gift_card_html, $applied_gift_card->gift_card_name, $remove_link, $applied_gift_card->used_amount);
                }
            }
        } else {
            $applied_cards = WC()->session->get('heartland_gift_card_applied');

            $this->removeAllGiftCardsFromSession();

            if (is_object($applied_cards) && count(get_object_vars($applied_cards)) > 0) {
                wc_add_notice(__('Sorry, we are unable to allow gift cards to be used when purchasing a subscription. Any gift cards already applied to the order have been cleared', 'wc_securesubmit'), 'notice');
            }
        }
    }

    public function updateOrderTotal($cart_total, $cart_object)
    {
        $gift_cards = WC()->session->get('heartland_gift_card_applied');

        if (is_object($gift_cards) && count(get_object_vars($gift_cards)) > 0) {
            $gift_card_totals = $this->getGiftCardTotals();
            $cart_total = $cart_total + $gift_card_totals;
        }

        return $cart_total;
    }

    protected function getGiftCardTotals()
    {
        $this->updateGiftCardTotals();
        $gift_cards = WC()->session->get('heartland_gift_card_applied');

        if (!empty($gift_cards)) {
            $total = 0;

            foreach ($gift_cards as $gift_card) {
                $total -= $gift_card->used_amount;
            }

            return $total;
        }
    }

    public function processGiftCardSale($card_number, $card_pin, $used_amount)
    {
        $card            = $this->giftCardObject($card_number, $card_pin);
        $rounded_amount  = round($used_amount, 2);
        $positive_amount = abs($rounded_amount);

        $response = $card->charge($positive_amount)
            ->withCurrency('USD')
            ->execute();

        return $response;
    }

    public function removeAllGiftCardsFromSession()
    {
        WC()->session->__unset('heartland_gift_card_applied');
        WC()->session->__unset('securesubmit_gift_card_object');
        WC()->session->__unset('securesubmit_data');
    }

    public function removeGiftCard($removed_card = null)
    {
        if (isset($_POST['securesubmit_card_id']) && empty($removed_card)) {
            $removed_card = wc_clean( $_POST['securesubmit_card_id'] );
        }

        $applied_cards = WC()->session->get('heartland_gift_card_applied');

        unset($applied_cards->{$removed_card});

        if (count((array) $applied_cards) > 0) {
            WC()->session->set('heartland_gift_card_applied', $applied_cards);
        } else {
            WC()->session->__unset('heartland_gift_card_applied');
        }

        if (isset($_POST['securesubmit_card_id']) && empty($removed_card)) {
            echo '';
            wp_die();
        }
    }

    public function processGiftCardVoid($processed_cards, $order_id)
    {
        if (!empty($processed_cards)) {
            foreach ($processed_cards as $card_id => $card) {
                try {
                    $response = Transaction::fromId($card->transactionId)
                        ->void()
                        ->execute();
                } catch (Exception $e) {
                }

                if (isset($response->responseCode) && $response->responseCode === '0') {
                    unset($processed_cards[$card_id]);
                }
            }
        } else {
            $response = false;
            delete_post_meta($order_id, '_securesubmit_used_card_data');
        }

        return $response;
    }
}
