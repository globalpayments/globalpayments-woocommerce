<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;

class AffirmBlock extends AbstractBuyNowPayLaterBlock {

	public function __construct() {
		$this->name = Affirm::PAYMENT_METHOD_ID;
	}
}
