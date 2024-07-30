<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ClickToPay;

class ClickToPayBlock extends AbstractDigitalWalletBlock {

	public function __construct() {
		$this->name = ClickToPay::PAYMENT_METHOD_ID;
	}
}
