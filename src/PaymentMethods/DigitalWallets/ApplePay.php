<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class ApplePay extends AbstractDigitalWallet {
	public const PAYMENT_METHOD_ID = 'globalpayments_applepay';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $default_title = 'Pay with Apple Pay';

	/**
	 * Supported credit card types
	 *
	 * @var array
	 */
	public $cc_types;

	/**
	 * Apple Merchant Id
	 *
	 * @var string
	 */
	public $apple_merchant_id;

	/**
	 * Apple Merchant Cert Path
	 *
	 * @var string
	 */
	public $apple_merchant_cert_path;

	/**
	 * Apple Merchant Key Path
	 *
	 * @var string
	 */
	public $apple_merchant_key_path;

	/**
	 * Apple Merchant Key PassPhrase
	 *
	 * @var string
	 */
	public $apple_merchant_key_passphrase;

	/**
	 * Apple Merchant Domain
	 *
	 * @var string
	 */
	public $apple_merchant_domain;

	/**
	 * Apple Merchant Display Name
	 *
	 * @var string
	 */
	public $apple_merchant_display_name;

	/**
	 * Button color
	 *
	 * @var string
	 */
	public $button_color;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Apple Pay', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Apple Pay via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function get_payment_method_form_fields() {
		return array(
			'apple_merchant_id'             => array(
				'title'             => __( 'Apple Merchant ID*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'apple_merchant_cert_path'      => array(
				'title'             => __( 'Apple Merchant Cert Path*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'apple_merchant_key_path'       => array(
				'title'             => __( 'Apple Merchant Key Path*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'apple_merchant_key_passphrase' => array(
				'title' => __( 'Apple Merchant Key Passphrase', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'password',
			),
			'apple_merchant_domain'         => array(
				'title'             => __( 'Apple Merchant Domain*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'apple_merchant_display_name'   => array(
				'title'             => __( 'Apple Merchant Display Name*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'cc_types'                      => array(
				'title'   => __( 'Accepted Cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'multiselectcheckbox',
				'class'   => 'accepted_cards',
				'css'     => 'width: 450px; height: 110px',
				'options' => array(
					CardType::VISA       => 'Visa',
					CardType::MASTERCARD => 'MasterCard',
					CardType::AMEX       => 'AMEX',
					CardType::DISCOVER   => 'Discover',
				),
				'default' => array(
					CardType::VISA,
					CardType::MASTERCARD,
					CardType::AMEX,
					CardType::DISCOVER,
				),
			),
			'button_color'                  => array(
				'title'       => __( 'Apple button color', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Button styling at checkout', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'apple-pay-button-white',
				'desc_tip'    => true,
				'options'     => array(
					'black'           => __( 'Black', 'globalpayments-gateway-provider-for-woocommerce' ),
					'white'           => __( 'White', 'globalpayments-gateway-provider-for-woocommerce' ),
					'white-with-line' => __( 'White with Outline', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_api_globalpayments_validate_merchant', array( $this, 'validate_merchant' ) );
	}

	/**
	 * @inheritdoc
	 */
	public function enqueue_payment_scripts() {
		$this->gateway->helper_script();

		wp_enqueue_script(
			'globalpayments-wc-applepay',
			Plugin::get_url( '/assets/frontend/js/globalpayments-applepay.js' ),
			array( 'wc-checkout', 'globalpayments-helper' ),
			Plugin::get_version(),
			true
		);

		wp_localize_script(
			'globalpayments-wc-applepay',
			'globalpayments_applepay_params',
			array(
				'id'                     => $this->id,
				'payment_method_options' => $this->get_frontend_payment_method_options(),
			)
		);

		wp_enqueue_style(
			'globalpayments-applepay',
			Plugin::get_url( '/assets/frontend/css/globalpayments-applepay.css' ),
			array(),
			Plugin::get_version()
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_frontend_payment_method_options() {
		return array(
			'apple_merchant_display_name' => $this->apple_merchant_display_name,
			'cc_types'                    => $this->cc_types,
			'country_code'                => wc_get_base_location()['country'],
			'validate_merchant_url'       => WC()->api_request_url( 'globalpayments_validate_merchant' ),
			'button_color'                => $this->button_color
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_mobile_type() {
		return EncyptedMobileType::APPLE_PAY;
	}

	public function validate_merchant() {
		$responseValidationUrl = json_decode( file_get_contents( 'php://input' ) );
		if ( empty( $responseValidationUrl ) ) {
			return null;
		}
		$validationUrl = $responseValidationUrl->validationUrl;

		if (
			! $this->apple_merchant_id ||
			! $this->apple_merchant_cert_path ||
			! $this->apple_merchant_key_path ||
			! $this->apple_merchant_domain ||
			! $this->apple_merchant_display_name
		) {
			return null;
		}
		$pemCrtPath = ABSPATH . $this->apple_merchant_cert_path;
		$pemKeyPath = ABSPATH . $this->apple_merchant_key_path;

		$validationPayload                       = array();
		$validationPayload['merchantIdentifier'] = $this->apple_merchant_id;
		$validationPayload['displayName']        = $this->apple_merchant_display_name;
		$validationPayload['initiative']         = 'web';
		$validationPayload['initiativeContext']  = $this->apple_merchant_domain;

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $validationUrl );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $validationPayload ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 300 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt( $ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
		curl_setopt( $ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
		curl_setopt( $ch, CURLOPT_SSLCERT, $pemCrtPath );
		curl_setopt( $ch, CURLOPT_SSLKEY, $pemKeyPath );

		if ( null !== $this->apple_merchant_key_passphrase ) {
			curl_setopt( $ch, CURLOPT_KEYPASSWD, $this->apple_merchant_key_passphrase );
		}

		$validationResponse = curl_exec( $ch );

		if ( false === $validationResponse ) {
			wp_send_json( [
				'error'   => true,
				'message' => curl_error( $ch ),
			] );
		}

		curl_close( $ch );

		wp_send_json( [
			'error'   => false,
			'message' => $validationResponse,
		] );
	}
}
