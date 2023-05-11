<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\GatewayProvider;

class GpiTransactionApiGateway extends AbstractGateway {
	/**
	* Gateway ID
	*/
	const GATEWAY_ID = 'globalpayments_transactionapi';

	public $gateway_provider = GatewayProvider::TRANSACTION_API;

	public $public_key;
	public $api_key;
	public $api_secret;
	public $account_credential;

	public $sandbox_public_key;
	public $sandbox_api_key;
	public $sandbox_api_secret;
	public $sandbox_account_credential;

	public $region;

	public $is_production;
	public $debug;

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Transaction API', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Transaction API', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_frontend_gateway_options() {
		return array(
			'X-GP-Api-Key'     => $this->get_credential_setting( 'public_key' ),
			'X-GP-Environment' => $this->is_production ? 'prod' : 'test'
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'apiKey'            => $this->get_credential_setting( 'api_key' ),
			'apiSecret'         => $this->get_credential_setting( 'api_secret' ),
			'accountCredential' => $this->get_credential_setting( 'account_credential' ),
			'country'           => $this->region,
			'apiVersion'        => '2021-04-08',
			'apiPartnerName'    => 'php_sdk_woocommerce',
			'debug'             => $this->debug,
		);
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production'              => array(
				'title'     => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'checkbox',
				'default'   => 'no',
			),
			'public_key'                 => array(
				'title'     => __( 'Live Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'text',
				'default'   => '',
				'class'     => 'live-toggle',
			),
			'api_key'                    => array(
				'title'     => __( 'Live API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'live-toggle',
			),
			'api_secret'                 => array(
				'title'     => __( 'Live API Secret', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'live-toggle',
			),
			'account_credential'         => array(
				'title'     => __( 'Live Account Credential', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'live-toggle',
			),
			'sandbox_public_key'         => array(
				'title'     => __( 'Sandbox Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'text',
				'default'   => '',
				'class'     => 'sandbox-toggle',
			),
			'sandbox_api_key'            => array(
				'title'     => __( 'Sandbox API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'sandbox-toggle',
			),
			'sandbox_api_secret'         => array(
				'title'     => __( 'Sandbox API Secret', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'sandbox-toggle',
			),
			'sandbox_account_credential' => array(
				'title'     => __( 'Sandbox Account Credential', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'password',
				'default'   => '',
				'class'     => 'sandbox-toggle',
			),
			'region'                     => array(
				'title'     => __( 'Region', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'      => 'select',
				'options'   => array(
				'US'        => 'United States',
				'CA'        => 'Canada',
				)
			),
			'debug'                      => array(
				'title'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Log all request to and from gateway. This can also log private data and should only be enabled in a development or stage environment.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);
	}

	public function get_first_line_support_email() {
		return 'onlinepayments@heartland.us';
	}

	protected function secure_payment_fields_asset_base_url() {
		if ( $this->is_production ) {
			return 'https://js.paygateway.com/secure_payment/v1';
		}

		return 'https://js.test.paygateway.com/secure_payment/v1';
	}
}
