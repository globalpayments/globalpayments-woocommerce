<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\MulticheckboxTrait;

defined( 'ABSPATH' ) || exit;

class ApplePayGateway extends AbstractGateway {

	use MulticheckboxTrait;

	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_applepay';

	/**
	 * SDK gateway provider
	 *
	 * @var string
	 */
	public $gateway_provider = GatewayProvider::GP_API;

	/**
	 * @inheritdoc
	 */
	public $is_digital_wallet = true;

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
	 * Payment Action
	 *
	 * @var string
	 */
	public $payment_action;

	/**
	 * Button color
	 *
	 * @var string
	 */
	public $button_color;

	public function __construct() {
		parent::__construct();

		$this->gateway = new GpApiGateway( true );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'GlobalPayments - Apple Pay', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Apple Pay gateway via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'api.integrations@globalpay.com';
	}

	public function get_frontend_gateway_options() {
		return array(
			'apple_merchant_display_name' => $this->apple_merchant_display_name,
			'cc_types'                    => $this->cc_types,
			'country_code'                => wc_get_base_location()['country'],
			'validate_merchant_url'       => WC()->api_request_url( 'globalpayments_validate_merchant' ),
			'googlepay_gateway_id'        => GooglePayGateway::GATEWAY_ID,
			'button_color'                => $this->button_color
		);
	}

	public function get_backend_gateway_options() {
		return $this->gateway->get_backend_gateway_options();
	}

	protected function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_api_globalpayments_validate_merchant', array( $this, 'validate_merchant' ) );
	}

	public function get_gateway_form_fields() {
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
					'VISA'       => 'Visa',
					'MASTERCARD' => 'MasterCard',
					'AMEX'       => 'AMEX',
					'DISCOVER'   => 'Discover',
				),
				'default' => array( 'VISA', 'MASTERCARD', 'AMEX', 'DISCOVER' )
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

	public function init_form_fields() {
		$this->form_fields = array_merge(
			array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Gateway', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default' => 'no',
				),
				'title'   => array(
					'title'             => __( 'Title', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'This controls the title which the user sees during checkout.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'           => __( 'Pay with Apple Pay', 'globalpayments-gateway-provider-for-woocommerce' ),
					'desc_tip'          => true,
					'custom_attributes' => array( 'required' => 'required' ),
				),
			),
			$this->get_gateway_form_fields(),
			array(
				'payment_action' => array(
					'title'       => __( 'Payment Action', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only for a delayed capture.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => self::TXN_TYPE_SALE,
					'desc_tip'    => true,
					'options'     => array(
						self::TXN_TYPE_SALE      => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
						self::TXN_TYPE_AUTHORIZE => __( 'Authorize only', 'globalpayments-gateway-provider-for-woocommerce' ),
					),
				),
			)
		);
	}

	public function payment_fields() {
		if ( is_checkout() ) {
			$this->tokenization_script();
			echo '<div>' . __( 'Pay with Apple Pay', 'globalpayments-gateway-provider-for-woocommerce' ) . '</div>';
		}
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

	public function mapResponseCodeToFriendlyMessage( $responseCode ) {
		if ( 'DECLINED' === $responseCode ) {
			return __( 'Your card has been declined by the bank.', 'globalpayments-gateway-provider-for-woocommerce' );
		}

		return __( 'An error occurred while processing the card.', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * Enqueues tokenization scripts from Global Payments and WooCommerce
	 *
	 * @return
	 */
	public function tokenization_script() {
		wp_enqueue_script(
			'globalpayments-helper',
			Plugin::get_url( '/assets/frontend/js/globalpayments-helper.js' ),
			array( 'jquery' ),
			WC()->version,
			true
		);

		wp_localize_script(
			'globalpayments-helper',
			'globalpayments_helper_params',
			array(
				'orderInfoUrl' => WC()->api_request_url( 'globalpayments_order_info' ),
				'order'        => array(
					'amount'   => $this->get_session_amount(),
					'currency' => get_woocommerce_currency(),
				)
			)
		);

		wp_enqueue_script(
			'globalpayments-wc-applepay',
			Plugin::get_url( '/assets/frontend/js/globalpayments-applepay.js' ),
			array( 'wc-checkout', 'globalpayments-helper' ),
			WC()->version,
			true
		);

		wp_localize_script(
			'globalpayments-wc-applepay',
			'globalpayments_applepay_params',
			array(
				'id'              => $this->id,
				'gateway_options' => $this->get_frontend_gateway_options(),
			)
		);

		wp_enqueue_style(
			'globalpayments-applepay',
			Plugin::get_url( '/assets/frontend/css/globalpayments-applepay.css' ),
			array(),
			WC()->version
		);
	}
}
