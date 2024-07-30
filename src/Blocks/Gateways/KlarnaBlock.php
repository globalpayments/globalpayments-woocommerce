<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;

class KlarnaBlock extends AbstractBuyNowPayLaterBlock {

	public function __construct() {
		$this->name = Klarna::PAYMENT_METHOD_ID;
	}
}
