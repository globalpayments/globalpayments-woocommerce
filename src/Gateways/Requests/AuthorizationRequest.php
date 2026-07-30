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

		$serverTransId = null;
		$dynamicDescriptor = null;
		$entryMode = null;
		if ( is_array( $this->data ) ) {
			$serverTransId = $this->data[ $this->gateway_id ]['serverTransId'] ?? null;
			$dynamicDescriptor = $this->data[ 'dynamic_descriptor' ] ?? null;
			$entryMode = $this->data['entry_mode'] ?? null;
		}

		$response = array(
			RequestArg::AMOUNT               => null !== $this->order ? $this->order->get_total() : null,
			RequestArg::CURRENCY             => null !== $this->order ? $this->order->get_currency() : null,
			RequestArg::CARD_DATA            => $token,
			RequestArg::SERVER_TRANS_ID      => $serverTransId,
			RequestArg::DYNAMIC_DESCRIPTOR   => $dynamicDescriptor,
		);

		if ( null !== $entryMode ) {
			$response[ RequestArg::ENTRY_MODE ] = $entryMode;
		}

		// Add installment data if installments are enabled
		$installments_enabled = $this->is_installments_enabled();
		
		if ( $installments_enabled && null !== $this->order ) {
			$installment_data = $this->get_installment_data();
			$response[ RequestArg::INSTALLMENT_DATA ] = $installment_data;
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
		
		// Check for either Mexico installments OR Visa installments
		$has_mexico_installments = (
			$gateway_settings['enabled'] === 'yes'
			&& isset( $gateway_settings['enable_installments'] )
			&& $gateway_settings['enable_installments'] === 'yes'
		);
		
		$has_visa_installments = (
			$gateway_settings['enabled'] === 'yes'
			&& isset( $gateway_settings['enable_visa_installments'] )
			&& $gateway_settings['enable_visa_installments'] === 'yes'
		);
		
		return $has_mexico_installments || $has_visa_installments;
	}

	/**
	 * Convert language code to 3-letter code for Visa Installments
	 *
	 * @param string $lang_code 2-letter or 3-letter language code
	 * @return string 3-letter language code (strictly 'eng' or 'fre')
	 */
	private function convert_language_code( $lang_code ): string {
		$language_map = array(
			'en'  => 'eng',
			'eng' => 'eng',
			'fr'  => 'fre',
			'fre' => 'fre',
		);

		if ( ! empty( $lang_code ) ) {
			$lang = strtolower( $lang_code );
			return $language_map[ $lang ] ?? $lang_code;
		}

		return $lang_code;
	}

	/**
	 * Get installment data for the transaction
	 *
	 * @return array|null
	 */
	protected function get_installment_data(): ?array {
		$installment_id = null;
		$installment_reference = null;
		$installment_lang = null;
		$installment_version = null;
		
		// First check if installment ID is passed explicitly in gateway data
		if ( isset( $this->data[ $this->gateway_id ]['installmentId'] ) ) {
			$installment_id = $this->data[ $this->gateway_id ]['installmentId'];
			$installment_reference = $this->data[ $this->gateway_id ]['installmentReference'] ?? null;
			$installment_lang = $this->data[ $this->gateway_id ]['installmentLang'] ?? null;
			$installment_version = $this->data[ $this->gateway_id ]['installmentVersion'] ?? null;
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
					if ( $payment_item->key === 'installmentLang' ) {
						$installment_lang = $payment_item->value;
					}
					if ( $payment_item->key === 'installmentVersion' ) {
						$installment_version = $payment_item->value;
					}
				}
			}
		}

		if ( !empty( $installment_id ) ) {
			$installment_data = array(
				'id' => $installment_id,
				'reference' => $installment_reference ?? $this->order->get_order_number()
			);
			
			// Add terms for Visa Installments (GB only) if language and version are provided
			if ( !empty( $installment_lang ) && !empty( $installment_version ) ) {
				$installment_data['terms'] = array(
					'language' => $this->convert_language_code( $installment_lang ),
					'version' => $installment_version
				);
			}
			
			return $installment_data;
		}
		
		// Check if token_response contains installment information
		if ( ! empty( $this->data[ $this->gateway_id ]['token_response'] ) ) {
			$token_response = json_decode(
				stripslashes( $this->data[ $this->gateway_id ]['token_response'] )
			);
			if ( isset( $token_response->installment ) ) {
				$installment_data = array(
					'id' => $token_response->installment->installmentId ?? null,
					'reference' => $token_response->installment->installmentReference
						?? $this->order->get_order_number()
				);
				
				// Add terms if language and version are available in token response
				if ( isset( $token_response->installment->language ) && isset( $token_response->installment->version ) ) {
					$installment_data['terms'] = array(
						'language' => $this->convert_language_code( $token_response->installment->language ),
						'version' => $token_response->installment->version
					);
				}
				
				return $installment_data;
			}
		}
		
		return null;
	}
}
