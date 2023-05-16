<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\TransactionModifier;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\AbstractDigitalWallet;

defined( 'ABSPATH' ) || exit;

class AuthorizationRequest extends AbstractRequest {
	public function get_transaction_type() {
		return $this->data['payment_action'];
	}

	public function do_request() {
		$payment_method = new CreditCardData();

		if ( isset ( $this->data[ $this->data['payment_method'] ]['dw_token'] ) ) {
			$payment_method->token = AbstractDigitalWallet::remove_slashes_from_token( $this->data[ $this->data['payment_method'] ]['dw_token'] );
		}
		$payment_method->mobileType = $this->data['mobile_type'];

		return $payment_method->{$this->data['payment_action']}( $this->order->get_total() )
		                      ->withCurrency( $this->order->get_currency() )
		                      ->withModifier( TransactionModifier::ENCRYPTED_MOBILE )
		                      ->withDynamicDescriptor( $this->data['dynamic_descriptor'] )
		                      ->execute();
	}

	public function get_args() {
		return array();
	}
}