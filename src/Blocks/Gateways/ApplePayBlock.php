<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

class ApplePayBlock extends AbstractDigitalWalletBlock {

	public function __construct() {
		$this->name = ApplePay::PAYMENT_METHOD_ID;
	}

	public function get_payment_method_script_handles() {
		$handles = parent::get_payment_method_script_handles();

		wp_enqueue_style(
			$handles[ 0 ],
			Plugin::get_url( '/assets/frontend/css/globalpayments-applepay.css' ),
			array(),
			Plugin::get_version()
		);

		return $handles;
	}
}
