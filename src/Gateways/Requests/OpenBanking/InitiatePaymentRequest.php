<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\OpenBanking;

use GlobalPayments\Api\Entities\Enums\RemittanceReferenceType;
use GlobalPayments\Api\PaymentMethods\BankPayment;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;

defined( 'ABSPATH' ) || exit;

class InitiatePaymentRequest extends AbstractRequest {

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_OB_AUTHORIZATION;
	}

	public function do_request() {
		$requestData = $this->data;
		$paymentMethod = new BankPayment();
		$remittanceValue = 'ORD' . $this->order->get_id();

		if ( !empty( $requestData['settings']['account_number'] ) ) {
			$paymentMethod->accountNumber = $requestData['settings']['account_number'];
		}

		if ( !empty( $requestData['settings']['iban'] ) ) {
			$paymentMethod->iban = $requestData['settings']['iban'];
		}

		if ( !empty( $requestData['settings']['sort_code'] ) ) {
			$paymentMethod->sortCode = $requestData['settings']['sort_code'];
		}

		if ( !empty( $requestData['settings']['countries'] ) ) {
			$paymentMethod->countries = explode( "|", $requestData['settings']['countries'] );
		}

		$paymentMethod->accountName     = $requestData['settings']['account_name'];
		$paymentMethod->returnUrl       = $requestData['globalpayments_openbanking']['returnUrl'];
		$paymentMethod->statusUpdateUrl = $requestData['globalpayments_openbanking']['statusUrl'];

		$builder = $paymentMethod->charge( $this->order->get_total() )
							->withCurrency( $this->order->get_currency() )
							->withOrderId( (string) $this->order->get_id() )
							->withRemittanceReference( RemittanceReferenceType::TEXT, $remittanceValue )
							->execute();

		return $builder;
	}

	public function get_args() {
		return array();
	}
}
