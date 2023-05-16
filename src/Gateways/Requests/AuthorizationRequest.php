<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\Api\Entities\Enums\PaymentMethodUsageMode;
use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class AuthorizationRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_AUTHORIZE;
	}

	public function get_args() {
		$paymentTokenData   = new PaymentTokenData( $this );
		$token              = $paymentTokenData->get_multi_use_token();
		$paymentMethodUsage = PaymentMethodUsageMode::MULTIPLE;
		if ( $token === null ) {
			$paymentMethodUsage = PaymentMethodUsageMode::SINGLE;
			$token              = $paymentTokenData->get_single_use_token();
		}

		$response = array(
			RequestArg::AMOUNT               => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::CURRENCY             => null !== $this->order ? $this->order->get_currency() : null,
			RequestArg::CARD_DATA            => $token,
			RequestArg::PAYMENT_METHOD_USAGE => $paymentMethodUsage,
			RequestArg::SERVER_TRANS_ID      => $this->data[ $this->gateway_id ]['serverTransId'] ?? null,
			RequestArg::DYNAMIC_DESCRIPTOR   => $this->data[ 'dynamic_descriptor' ],
		);

		if ( isset ( $this->data['entry_mode'] ) ) {
			$response[ RequestArg::ENTRY_MODE ] = $this->data['entry_mode'];
		}

		return $response;
	}
}
