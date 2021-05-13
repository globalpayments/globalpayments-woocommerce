<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class AuthorizationRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_AUTHORIZE;
	}

	public function get_args() {
		$token = ( new PaymentTokenData( $this ) )->get_token();

		return array(
			RequestArg::AMOUNT          => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::CURRENCY        => null !== $this->order ? $this->order->get_currency() : null,
			RequestArg::CARD_DATA       => $token,
			RequestArg::SERVER_TRANS_ID => $this->data[ $this->gateway_id ]['serverTransId'] ?? null,
			RequestArg::PARES           => $this->data[ $this->gateway_id ]['PaRes'] ?? null,
		);
	}
}
