<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\GooglePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

class GooglePayBlock extends AbstractDigitalWalletBlock {

	public function __construct() {
		$this->name = GooglePay::PAYMENT_METHOD_ID;
	}

	public function get_payment_method_script_handles() {
		$handles = parent::get_payment_method_script_handles();

		wp_enqueue_script(
			'globalpayments-googlepay',
			( 'https://pay.google.com/gp/p/js/pay.js' ),
			array(),
			WC()->version,
			true
		);

		wp_enqueue_style(
			$handles[ 0 ],
			Plugin::get_url( 'resources/css/frontend/components/digitalWallets/googlePayComponent.css' ),
			array(),
			Plugin::get_version()
		);

		$handles[] = 'globalpayments-googlepay';

		return $handles;
	}
}
