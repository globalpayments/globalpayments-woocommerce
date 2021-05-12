<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;

defined( 'ABSPATH' ) || exit;

class GeniusGateway extends AbstractGateway {
	public $gateway_provider = GatewayProvider::GENIUS;

	/**
	 * Merchant location's Merchant Name
	 *
	 * @var string
	 */
	public $merchant_name;

	/**
	 * Merchant location's Site ID
	 *
	 * @var string
	 */
	public $merchant_site_id;

	/**
	 * Merchant location's Merchant Key
	 *
	 * @var string
	 */
	public $merchant_key;

	/**
	 * Merchant location's Web API Key
	 *
	 * @var string
	 */
	public $web_api_key;

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
			'merchant_name'    => array(
				'title'   => __( 'Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'merchant_site_id' => array(
				'title'   => __( 'Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'merchant_key'     => array(
				'title'   => __( 'Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'web_api_key'      => array(
				'title'       => __( 'Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your credentials from your TSYS Genius account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'is_production'    => array(
				'title'   => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'   => __( 'Go Live', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'webApiKey' => $this->web_api_key,
			'env'       => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'merchantName'   => $this->merchant_name,
			'merchantSiteId' => $this->merchant_site_id,
			'merchantKey'    => $this->merchant_key,
			'environment'    => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
		);
	}
    public function cvn_rejection_conditions()
    {}

    public function avs_rejection_conditions()
    {}

}
