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
		$paymentTokenData   = new PaymentTokenData( $this );
		$token              = $paymentTokenData->get_multi_use_token();
		if ( null === $token ) {
			$token          = $paymentTokenData->get_single_use_token();
		}

		$response = array(
			RequestArg::AMOUNT               => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::CURRENCY             => null !== $this->order ? $this->order->get_currency() : null,
			RequestArg::CARD_DATA            => $token,
			RequestArg::SERVER_TRANS_ID      => $this->data[ $this->gateway_id ]['serverTransId'] ?? null,
			RequestArg::DYNAMIC_DESCRIPTOR   => $this->data[ 'dynamic_descriptor' ],
		);

		if ( isset ( $this->data['entry_mode'] ) ) {
			$response[ RequestArg::ENTRY_MODE ] = $this->data['entry_mode'];
		}

		// Add installment data if installments are enabled
		if ( $this->is_installments_enabled() && null !== $this->order ) {
			$response[ RequestArg::INSTALLMENT_DATA ] = $this->get_installment_data();
		}

		return $response;
	}

	/**
	 * Check if installments are enabled
	 *
	 * @return bool
	 */
	protected function is_installments_enabled(): bool {
		$gateway_settings = get_option( 'woocommerce_globalpayments_gpapi_settings', array() );
		return isset( $gateway_settings['enable_installments'] ) && $gateway_settings['enable_installments'] === 'yes';
	}

	/**
	 * Get installment data for the transaction
	 *
	 * @return array|null
	 */
	protected function get_installment_data(): ?array {
		$installment_id = null;
		$installment_reference = null;
		
		// First check if installment ID is passed explicitly in gateway data
		if ( isset( $this->data[ $this->gateway_id ]['installmentId'] ) ) {
			$installment_id = $this->data[ $this->gateway_id ]['installmentId'];
			$installment_reference = $this->data[ $this->gateway_id ]['installmentReference'] ?? null;
		}

		// For WooCommerce Blocks, check payment_data array
		if (
			empty( $installment_id )
			&& isset( $this->data['payment_data'] )
			&& is_array( $this->data['payment_data'] )
		) {
			foreach ( $this->data['payment_data'] as $payment_item ) {
				if ( is_object( $payment_item ) && !empty( $payment_item->key ) ) {
					if ( $payment_item->key === 'installmentId' ) {
						$installment_id = $payment_item->value;
					}
					if ( $payment_item->key === 'installmentReference' ) {
						$installment_reference = $payment_item->value;
					}
				}
			}
		}

		if ( !empty( $installment_id ) ) {
			return array(
				'id' => $installment_id,
				'reference' => $installment_reference ?? $this->order->get_order_number()
			);
		}
		
		// Check if token_response contains installment information
		if ( ! empty( $this->data[ $this->gateway_id ]['token_response'] ) ) {
			$token_response = json_decode(
				stripslashes( $this->data[ $this->gateway_id ]['token_response'] )
			);
			if ( isset( $token_response->installment ) ) {
				return array(
					'id' => $token_response->installment->installmentId ?? null,
					'reference' => $token_response->installment->installmentReference
						?? $this->order->get_order_number()
				);
			}
		}
		
		return null;
	}
}
