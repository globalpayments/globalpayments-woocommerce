<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\Api\Entities\Enums\PaymentMethodUsageMode;
use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\TransactionApiGateway;

defined( 'ABSPATH' ) || exit;

class VerifyRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_VERIFY;
	}

	public function get_args() {
		$token = ( new PaymentTokenData( $this ) )->get_token();

		if ( GpApiGateway::GATEWAY_ID === $this->gateway_id ) {
			return array(
				RequestArg::CARD_DATA       => $token,
				RequestArg::CURRENCY        => null !== $this->order ? $this->order->get_currency() : get_woocommerce_currency(),
				RequestArg::SERVER_TRANS_ID => $this->data[ $this->gateway_id ]['serverTransId'] ?? null,
			);
		}
		if ($this->gateway_id === 'globalpayments_transactionapi') {
		    return array(
			RequestArg::CARD_DATA               => $token,
			RequestArg::PAYMENT_METHOD_USAGE    => PaymentMethodUsageMode::SINGLE,
			RequestArg::CURRENCY                => null !== $this->order ? $this->order->get_currency() : get_woocommerce_currency(),
			);
		}

		return array(
			RequestArg::CARD_DATA => $token,
		);
	}
}
