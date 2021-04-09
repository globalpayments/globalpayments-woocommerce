<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class TransactionDetailRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_REPORT_TXN_DETAILS;
	}

	public function get_args() {
		$gateway_id = $this->order->get_transaction_id();

		return array(
			RequestArg::AMOUNT     => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::CURRENCY   => null !== $this->order ? $this->order->get_currency() : null,
			RequestArg::GATEWAY_ID => $gateway_id,
		);
	}
}
