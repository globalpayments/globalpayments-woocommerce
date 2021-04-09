<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Data;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;
use WC_Payment_Tokens;
use WC_Payment_Token_CC;

class PaymentTokenData {
	const KEY_SHOULD_SAVE_TOKEN = 'should_save_for_later';

	protected $card_type_map = array(
		'mastercard' => 'mastercard',
		'visa'       => 'visa',
		'discover'   => 'discover',
		'amex'       => 'american express',
		'diners'     => 'diners',
		'jcb'        => 'jcb',
	);

	/**
	 * Current request
	 *
	 * @var RequestInterface
	 */
	protected $request;

	/**
	 * Used w/TransIT gateway
	 *
	 * @var string
	 */
	public static $tsepCvv = null;

	/**
	 * Standardize getting single- and multi-use token data
	 *
	 * @param RequestInterface $request
	 *
	 * @return
	 */
	public function __construct( RequestInterface $request = null ) {
		$this->request = $request;
	}

	public function get_token() {
		$token = $this->get_multi_use_token();

		if ( null === $token ) {
			$token = $this->get_single_use_token();
		}

		return $token;
	}

	public function save_new_token( $multi_use_token, $card_brand_txn_id = null ) {
		$user_id        = get_current_user_id();
		$current_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $this->request->gateway_id );

		$token = $this->get_single_use_token();

		if ( !empty( $token ) ) {
			// a card number should only have a single token stored
			foreach ( $current_tokens as $t ) {
				if ( $t->get_token() === $multi_use_token ) {
					$t->delete( true );
				}
			}

			if ( ! $token->get_meta( self::KEY_SHOULD_SAVE_TOKEN, true ) ) {
				return;
			}

			$token->set_token( $multi_use_token );
			$token->add_meta_data( 'card_brand_txn_id', $card_brand_txn_id );
			$token->set_user_id( $user_id );
			$token->set_gateway_id( $this->request->gateway_id );
			$token->add_meta_data( self::KEY_SHOULD_SAVE_TOKEN, false, true );
			$token->save();
		}
	}

	public function get_single_use_token() {
		if ( null === $this->request ) {
			return null;
		}

		$gateway      = $this->request->get_request_data( 'payment_method' );
		$request_data = $this->request->get_request_data( $gateway );
		if ( ! isset( $request_data['token_response'] ) ) {
		    return null;
		}

		$data = json_decode( stripslashes( $request_data['token_response'] ) );

		if ( empty( $data ) ) {
			return null;
		}

		$token = new WC_Payment_Token_CC();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName
		$token->add_meta_data( self::KEY_SHOULD_SAVE_TOKEN, $this->get_should_save_for_later(), true );
		$token->set_token( $data->paymentReference );

		if ( isset( $data->details->cardLast4 ) ) {
			$token->set_last4( $data->details->cardLast4 );
		}

		if ( isset( $data->details->expiryYear ) ) {
			$token->set_expiry_year( $data->details->expiryYear );
		}

		if ( isset( $data->details->expiryMonth ) ) {
			$token->set_expiry_month( $data->details->expiryMonth );
		}

		static::$tsepCvv = isset( $data->details->cardSecurityCode ) ? $data->details->cardSecurityCode : null;

		if ( isset( $data->details->cardType ) && isset( $this->card_type_map[ $data->details->cardType ] ) ) {
			$token->set_card_type( $this->card_type_map[ $data->details->cardType ] );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName

		return $token;
	}

	public function get_multi_use_token() {
		if ( null === $this->request ) {
			return null;
		}

		$gateway  = $this->request->get_request_data( 'payment_method' );
		$token_id = $this->request->get_request_data( sprintf( 'wc-%s-payment-token', $gateway ) );

		if ( 'new' === $token_id ) {
			return null;
		}

		$token = WC_Payment_Tokens::get( $token_id );

		if ( null === $token || $token->get_user_id() !== get_current_user_id() ) {
			return null;
		}

		if ( $token->get_meta( self::KEY_SHOULD_SAVE_TOKEN, true ) ) {
			$token->add_meta_data( self::KEY_SHOULD_SAVE_TOKEN, false, true );
		}

		return $token;
	}

	protected function get_should_save_for_later() {
		$gateway = $this->request->get_request_data( 'payment_method' );
		return // Verify transactions always mean we're storing a token
			$this->request->get_transaction_type() === AbstractGateway::TXN_TYPE_VERIFY ||
			// Merchant has enabled card storage. Customer has elected to store card.
			$this->request->get_request_data( sprintf( 'wc-%s-new-payment-method', $gateway ) ) === 'true';
	}
}
