<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;

defined( 'ABSPATH' ) || exit;

class GeniusGateway extends AbstractGateway {
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

	public function configure_method_settings() {
		$this->id                 = 'globalpayments_genius';
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
			),
			'merchant_site_id' => array(
				'title'   => __( 'Live Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'merchant_key'     => array(
				'title'   => __( 'Live Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
			),
			'web_api_key'      => array(
				'title'       => __( 'Live Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
			),
			'sandbox_merchant_name'    => array(
				'title'   => __( 'Sandbox Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'sandbox_merchant_site_id' => array(
				'title'   => __( 'Sandbox Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'sandbox_merchant_key'     => array(
				'title'   => __( 'Sandbox Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
			),
			'sandbox_web_api_key'      => array(
				'title'       => __( 'Sandbox Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
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
}
