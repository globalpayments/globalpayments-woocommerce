<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class CaptureAuthorizationRequest extends AbstractRequest {

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_CAPTURE;
	}

	public function get_args() {
		$gateway_id    = $this->order->get_transaction_id();

		return array(
			RequestArg::GATEWAY_ID  => $gateway_id
		);
	}
}
