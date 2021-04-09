<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class ReversalRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_REVERSAL;
	}

	public function get_args() {

		$gateway_id  = $this->order->get_transaction_id();
		$description = $this->data['refund_reason'];

		$original_amount = wc_format_decimal( $this->order->get_total(), 2 );
		$total_refunded  = wc_format_decimal( $this->order->get_total_refunded(), 2 );
		$new_amount      = wc_format_decimal( $original_amount - $total_refunded, 2 );

		return array(
			RequestArg::AMOUNT      => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::AUTH_AMOUNT => $new_amount,
			RequestArg::GATEWAY_ID  => $gateway_id,
			RequestArg::DESCRIPTION => $description,
		);
	}
}
