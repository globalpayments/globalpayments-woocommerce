<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class TransitGateway extends AbstractGateway {
	/**
	 * Merchant location's Merchant ID
	 *
	 * @var string
	 */
	public $merchant_id;

	/**
	 * Merchant location's User ID
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $user_id;

	/**
	 * Merchant location's Password
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $password;

	/**
	 * Merchant location's Device ID
	 *
	 * @var string
	 */
	public $device_id;

	/**
	 * Device ID for TSEP entity specifically
	 *
	 * @var string
	 */
	public $tsep_device_id;

	/**
	 * Merchant location's Transaction Key
	 *
	 * @var string
	 */
	public $transaction_key;

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
	public $developer_id = '003226G001';

	/**
	 * SDK gateway provider
	 *
	 * @var string
	 */
	public $gateway_provider = GatewayProvider::TRANSIT;

	public function configure_method_settings() {
		$this->id                 = 'globalpayments_transit';
		$this->method_title       = __( 'TSYS TransIT', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the TSYS TransIT gateway with TSEP', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'securesubmitcert@e-hps.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'merchant_id'     => array(
				'title'       => __( 'Merchant ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'user_id'         => array(
				'title'       => __( 'MultiPass User ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'password'        => array(
				'title'       => __( 'Password', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'device_id'       => array(
				'title'       => __( 'Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'tsep_device_id'       => array(
				'title'       => __( 'TSEP Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'transaction_key' => array(
				'title'       => __( 'Transaction Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'is_production'   => array(
				'title'   => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);
	}

	/**
	 * Process admin options
	 *
	 * On save, we'll prefetch a TransIT `transaction_key` using the `merchant_id`, `user_id`, and `password`,
	 * persisting the `transaction_key` and ignoring the `user_id` and `password`.
	 *
	 * @return
	 */
	public function process_admin_options() {
		parent::process_admin_options();

		$option_name = sprintf( 'woocommerce_%s_settings', $this->id );
		$settings    = get_option( $option_name );

		$this->user_id  = $settings['user_id'];
		$this->password = $settings['password'];

		if ( empty( $this->transaction_key ) && ! empty( $this->user_id ) && ! empty( $this->password ) ) {
			$transaction_key             = $this->create_transaction_key();
			$settings['transaction_key'] = $transaction_key;
		}

		$settings['user_id']  = null;
		$settings['password'] = null;
		update_option( $option_name, $settings );
	}

	public function get_frontend_gateway_options() {
		return array(
			'deviceId' => $this->tsep_device_id,
			'manifest' => $this->create_manifest(),
			'env'      => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'merchantId'     => $this->merchant_id,
			'username'       => $this->user_id, // only needed to create transation key
			'password'       => $this->password, // only needed to create transation key
			'transactionKey' => $this->transaction_key,
			'tsepDeviceId'	 => $this->tsep_device_id,
			'deviceId'       => $this->device_id,
			'developerId'    => '003226G004', // provided during certification
			'environment'    => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
		);
	}

	protected function create_transaction_key() {
		$request  = $this->prepare_request( self::TXN_TYPE_CREATE_TRANSACTION_KEY );
		$response = $this->submit_request( $request );
		// @phpcs:ignore WordPress.NamingConventions.ValidVariableName
		return $response->transactionKey;
	}

	protected function create_manifest() {
		$request  = $this->prepare_request( self::TXN_TYPE_CREATE_MANIFEST );
		$response = $this->submit_request( $request );
		return $response;
	}

	/**
	 * Handle online refund requests via WP Admin > WooCommerce > Edit Order
	 *
	 * @param int $order_id
	 * @param null|number $amount
	 * @param string $reason
	 *
	 * @return array
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$txn_type 		= self::TXN_TYPE_REFUND;
		$order			= new WC_Order( $order_id );
		$request		= $this->prepare_request( $txn_type, $order );
		$response		= $this->submit_request( $request );
		$is_successful	= $this->handle_response( $request, $response );

		return $is_successful;
	}
    public function cvn_rejection_conditions()
    {}

    public function avs_rejection_conditions()
    {}

}
