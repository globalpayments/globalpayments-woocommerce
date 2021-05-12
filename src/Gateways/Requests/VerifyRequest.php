<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class VerifyRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_VERIFY;
	}

	public function get_args() {
		$token = ( new PaymentTokenData( $this ) )->get_token();

		return array(
			RequestArg::CARD_DATA => $token,
		);
	}
}
