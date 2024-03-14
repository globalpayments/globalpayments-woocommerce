<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\Apm;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\Api\PaymentMethods\AlternativePaymentMethod;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;

defined( 'ABSPATH' ) || exit;

class InitiatePaymentRequest extends AbstractRequest {

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_PAYPAL_INITIATE;
	}

	public function do_request() {
		$requestData = $this->data;
		$paymentMethodId = $requestData['payment_method'];

		$paymentMethod = new AlternativePaymentMethod( AlternativePaymentType::PAYPAL );

		$paymentMethod->descriptor        = 'ORD' . $this->order->get_id();
		$paymentMethod->accountHolderName = $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name();
		$paymentMethod->country           = $this->order->get_billing_country();
		$paymentMethod->returnUrl         = $requestData[$paymentMethodId]['returnUrl'];
		$paymentMethod->statusUpdateUrl   = $requestData[$paymentMethodId]['statusUrl'];
		$paymentMethod->cancelUrl         = $requestData[$paymentMethodId]['cancelUrl'];

		return $paymentMethod->{$requestData['payment_action']}( $this->order->get_total() )
			->withCurrency( $this->order->get_currency() )
			->withOrderId( (string) $this->order->get_id() )
			->execute();
	}

	public function get_args() {
		return array();
	}
}
