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

    protected $card_type_map_transaction_api = array(
        'MasterCard'        => 'mastercard',
        'Visa'              => 'visa',
        'Discover'          => 'discover',
        'American Express'   => 'american express',
        'Diners Club'        => 'diners',
        'JCB'               => 'jcb',
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

        return $this->build_single_use_token($data);
	}

    /**
     * @param $data
     * @return WC_Payment_Token_CC
     */
    private function build_single_use_token($data): WC_Payment_Token_CC {
        $token = new WC_Payment_Token_CC();

        // phpcs:disable WordPress.NamingConventions.ValidVariableName
        $token->add_meta_data(self::KEY_SHOULD_SAVE_TOKEN, $this->get_should_save_for_later(), true);
        if (isset($data->paymentReference)) {
            $this->fill_token_fields($token, $data);
        } else {
            $this->fill_token_fields_for_transaction_api($token, $data);
        }
        return $token;
        // phpcs:enable WordPress.NamingConventions.ValidVariableName
    }

    /**
     * @param WC_Payment_Token_CC $token
     * @param $data
     * @return void
     */
    public function fill_token_fields(WC_Payment_Token_CC $token, $data): void {
        $token->set_token($data->paymentReference);

        if (isset($data->details->cardLast4)) {
            $token->set_last4($data->details->cardLast4);
        }

        if (isset($data->details->expiryYear)) {
            $token->set_expiry_year($data->details->expiryYear);
        }

        if (isset($data->details->expiryMonth)) {
            $token->set_expiry_month($data->details->expiryMonth);
        }

        static::$tsepCvv = isset($data->details->cardSecurityCode) ? $data->details->cardSecurityCode : null;

        if (isset($data->details->cardType) && isset($this->card_type_map[$data->details->cardType])) {
            $token->set_card_type($this->card_type_map[$data->details->cardType]);
        }
    }

    /**
     * @param WC_Payment_Token_CC $token
     * @param $data
     * @return void
     */
    public function fill_token_fields_for_transaction_api(WC_Payment_Token_CC $token, $data): void {
        $token->set_token($data->temporary_token);

        $card = $data->card;
        if (isset($card->masked_card_number)) {
            $token->set_last4(substr($card->masked_card_number, -4));
        }

        if (isset($card->expiry_year)) {
            $token->set_expiry_year($card->expiry_year + 2000);
        }

        if (isset($card->expiry_month)) {
            $token->set_expiry_month($card->expiry_month);
        }

        if (isset($card->type) && isset($this->card_type_map_transaction_api[$card->type])) {
            $token->set_card_type($this->card_type_map_transaction_api[$card->type]);
        }
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
		if ( ! isset( $token ) ) {
			return null;
		}

		if ( $token->get_user_id() !== get_current_user_id() && ! wc_current_user_has_role( 'administrator' ) ) {
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
