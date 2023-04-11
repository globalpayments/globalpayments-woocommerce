<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class TransactionDetailRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_REPORT_TXN_DETAILS;
	}

	public function get_args() {
		$gateway_id = $this->data['txn_id'] ?? null;

		return array(
			RequestArg::GATEWAY_ID => $gateway_id,
		);
	}
}
