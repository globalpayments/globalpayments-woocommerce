<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards;

use GlobalPayments\Api\PaymentMethods\GiftCard;

defined('ABSPATH') || exit;

class HeartlandGiftCard extends GiftCard
{
    public $gift_card_name;
    public $gift_card_id;
    public $temp_balance;
    public $used_amount;
}
