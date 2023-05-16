<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\MulticheckboxTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;

defined( 'ABSPATH' ) || exit;

abstract class AbstractDigitalWallet extends AbstractPaymentMethod {
	use MulticheckboxTrait;

	/**
	 * @return GlobalPayments\Api\Entities\Enums\EncyptedMobileType
	 */
	abstract public function get_mobile_type();

	/**
	 * @inheritdoc
	 */
	public function get_request_type() {
		return AbstractGateway::TXN_TYPE_DW_AUTHORIZATION;
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment_before_gateway_request( &$request, $order ) {
		$request->set_request_data( array(
			'mobile_type'        => $this->get_mobile_type(),
			'payment_action'     => $this->payment_action,
			'dynamic_descriptor' => $this->gateway->txn_descriptor,
		) );
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment_after_gateway_response( Transaction $gateway_response, $is_successful, \WC_Order $order ) {
		if ( ! $is_successful ) {
			return;
		}

		$note_text = sprintf(
			'%1$s%2$s %3$s. Transaction ID: %4$s.',
			get_woocommerce_currency_symbol( $order->get_currency() ),
			$order->get_total(),
			$this->payment_action == AbstractGateway::TXN_TYPE_AUTHORIZE ?
				__( 'authorized', 'globalpayments-gateway-provider-for-woocommerce' ) :
				__( 'charged', 'globalpayments-gateway-provider-for-woocommerce' ),
			$order->get_transaction_id()
		);
		$order->add_order_note( $note_text );
	}

	public static function remove_slashes_from_token( string $token ) {
		$token = str_replace( '\\"', '"', $token );
		$token = str_replace( '\\"', '"', $token );
		$token = str_replace( '\\\\\\\\', '\\', $token );

		return $token;
	}
}
