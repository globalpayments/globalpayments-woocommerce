<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class RefundRequest extends AbstractRequest {

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_REFUND;
	}

	public function get_args() {
		$gateway_id    = $this->order->get_transaction_id();
		$description   = $this->data['refund_reason'];
		$refund_amount = wc_format_decimal( $this->data['refund_amount'] );

		return array(
			RequestArg::CURRENCY    => $this->order->get_currency(),
			RequestArg::AMOUNT      => ! empty( $refund_amount ) ? $refund_amount : null,
			RequestArg::GATEWAY_ID  => $gateway_id,
			RequestArg::DESCRIPTION => $description,
		);
	}
}
