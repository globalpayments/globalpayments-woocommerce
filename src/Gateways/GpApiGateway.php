<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;

defined( 'ABSPATH' ) || exit;

class GpApiGateway extends AbstractGateway {
	/**
	 * SDK gateway provider
	 *
	 * @var string
	 */
	public $gateway_provider = GatewayProvider::GP_API;

	/**
	 * App ID
	 *
	 * @var string
	 */
	public $app_id;

	/**
	 * App Key
	 *
	 *
	 * @var string
	 */
	public $app_key;

	/**
	 * Should live payments be accepted
	 *
	 * @var bool
	 */
	public $is_production;

	/**
	 * Integration's Developer ID
	 *
	 * @var string
	 */
	public $developer_id = '';

	public function configure_method_settings() {
		$this->id                 = 'globalpayments_gpapi';
		$this->method_title       = __( 'GP-API', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Global Payments API (GP-API) gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'api.integrations@globalpay.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'app_id' => array(
				'title'       => __( 'App Id', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'Get your App Id from your <a href="https://developer.globalpay.com/user/register" target="_blank">Global Payments Developer Account</a>.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'default'     => '',
			),
			'app_key' => array(
				'title'       => __( 'App Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __(
					'Get your App Key from your <a href="https://developer.globalpay.com/user/register" target="_blank">Global Payments Developer Account</a>.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'default'     => '',
			),
			'is_production' => array(
				'title'   => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'accessToken' => $this->get_access_token(),
			'env'         => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'AppId'       => $this->app_id,
			'AppKey'      => $this->app_key,
			'developerId' => '',
			'environment' => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
		);
	}

	protected function get_access_token() {
		$request  = $this->prepare_request(self::TXN_TYPE_GET_ACCESS_TOKEN);
		$response = $this->submit_request($request);

		return $response->token;
	}

	public function mapResponseCodeToFriendlyMessage( $responseCode ) {
		if ( 'DECLINED' === $responseCode ) {
			return __( 'Your card has been declined by the bank.', 'globalpayments-gateway-provider-for-woocommerce' );
		}

		return __( 'An error occurred while processing the card.', 'globalpayments-gateway-provider-for-woocommerce' );
	}
    public function cvn_rejection_conditions()
    {}

    public function avs_rejection_conditions()
    {}

}
