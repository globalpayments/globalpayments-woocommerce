<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class GetAccessTokenRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_GET_ACCESS_TOKEN;
	}

	public function get_args() {
		return array(
			RequestArg::PERMISSIONS => array(
				'PMT_POST_Create_Single',
			),
		);
	}
}
