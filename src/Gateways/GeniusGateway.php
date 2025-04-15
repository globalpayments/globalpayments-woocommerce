<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use Exception;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;

defined( 'ABSPATH' ) || exit;

class GeniusGateway extends AbstractGateway {
	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_genius';

	public $gateway_provider = GatewayProvider::GENIUS;

	/**
	 * Live Merchant location's Merchant Name
	 *
	 * @var string
	 */
	public $merchant_name;

	/**
	 * Live Merchant location's Site ID
	 *
	 * @var string
	 */
	public $merchant_site_id;

	/**
	 * Live Merchant location's Merchant Key
	 *
	 * @var string
	 */
	public $merchant_key;

	/**
	 * Live Merchant location's Web API Key
	 *
	 * @var string
	 */
	public $web_api_key;

	/**
	 * Sandbox Merchant location's Merchant Name
	 *
	 * @var string
	 */
	public $sandbox_merchant_name;

	/**
	 * Sandbox Merchant location's Site ID
	 *
	 * @var string
	 */
	public $sandbox_merchant_site_id;

	/**
	 * Sandbox Merchant location's Merchant Key
	 *
	 * @var string
	 */
	public $sandbox_merchant_key;

	/**
	 * Sandbox Merchant location's Web API Key
	 *
	 * @var string
	 */
	public $sandbox_web_api_key;

	/**
	 * Should live payments be accepted
	 *
	 * @var bool
	 */
	public $is_production;

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'TSYS Genius', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the TSYS Genius gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'securesubmitcert@e-hps.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production'    => array(
				'title'   => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'description' => __( 'Get your credentials from your TSYS Genius account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default' => 'no',
			),
			'merchant_name'    => array(
				'title'   => __( 'Live Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'live-toggle',
			),
			'merchant_site_id' => array(
				'title'   => __( 'Live Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'live-toggle',
			),
			'merchant_key'     => array(
				'title'   => __( 'Live Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'class' => 'live-toggle',
			),
			'web_api_key'      => array(
				'title'       => __( 'Live Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'sandbox_merchant_name'    => array(
				'title'   => __( 'Sandbox Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_merchant_site_id' => array(
				'title'   => __( 'Sandbox Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_merchant_key'     => array(
				'title'   => __( 'Sandbox Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_web_api_key'      => array(
				'title'       => __( 'Sandbox Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'webApiKey' => $this->get_credential_setting( 'web_api_key' ),
			'env'       => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'merchantName'   => $this->get_credential_setting( 'merchant_name' ),
			'merchantSiteId' => $this->get_credential_setting( 'merchant_site_id' ),
			'merchantKey'    => $this->get_credential_setting( 'merchant_key' ),
			'environment'    => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
		);
	}

	/**
	 * Overrides parent class method
	 *
	 * @param int    $order_id
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws ApiException
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		$request = $this->prepare_request( self::TXN_TYPE_REFUND, $order );

		if ( null != $amount ) {
			$amount = str_replace( ',', '.', $amount );
			$amount = number_format( (float) round( $amount, 2, PHP_ROUND_HALF_UP ), 2, '.', '' );
			if ( ! is_numeric( $amount ) ) {
				throw new Exception( esc_html__( 'Refund amount must be a valid number', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		}
		$request->set_request_data( array(
			'refund_amount' => $amount,
			'refund_reason' => $reason,
		) );
		$request_args = $request->get_args();
		if ( 0 >= (float)$request_args[ RequestArg::AMOUNT ] ) {
			throw new Exception( esc_html__( 'Refund amount must be greater than zero.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			$note_text = sprintf(
                /* translators: %1$s%2$s was reversed or refunded. Transaction ID: %3$s */
				esc_html__( '%1$s%2$s was reversed or refunded. Transaction ID: %3$s ', 'globalpayments-gateway-provider-for-woocommerce' ),
				get_woocommerce_currency_symbol(), $amount, $response->transactionReference->transactionId
			);

			$order->add_order_note( $note_text );
		}

		return $is_successful;
	}
}
