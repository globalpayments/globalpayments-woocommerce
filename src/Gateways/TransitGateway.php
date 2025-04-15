<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class TransitGateway extends AbstractGateway {
	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_transit';

	/**
	 * Live Merchant location's Merchant ID
	 *
	 * @var string
	 */
	public $merchant_id;

	/**
	 * Live Merchant location's User ID
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $user_id;

	/**
	 * Live Merchant location's Password
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $password;

	/**
	 * Live Merchant location's Device ID
	 *
	 * @var string
	 */
	public $device_id;

	/**
	 * Live Device ID for TSEP entity specifically
	 *
	 * @var string
	 */
	public $tsep_device_id;

	/**
	 * Live Merchant location's Transaction Key
	 *
	 * @var string
	 */
	public $transaction_key;

	/**
	 * Sandbox Merchant location's Merchant ID
	 *
	 * @var string
	 */
	public $sandbox_merchant_id;

	/**
	 * Sandbox Merchant location's User ID
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $sandbox_user_id;

	/**
	 * Sandbox Merchant location's Password
	 *
	 * Note: only needed to create transation key
	 *
	 * @var string
	 */
	public $sandbox_password;

	/**
	 * Sandbox Merchant location's Device ID
	 *
	 * @var string
	 */
	public $sandbox_device_id;

	/**
	 * Sandbox Device ID for TSEP entity specifically
	 *
	 * @var string
	 */
	public $sandbox_tsep_device_id;

	/**
	 * Sandbox Merchant location's Transaction Key
	 *
	 * @var string
	 */
	public $sandbox_transaction_key;

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

	/**
	 * Should debug
	 *
	 * @var bool
	 */
	public $debug;

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'TSYS TransIT', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the TSYS TransIT gateway with TSEP', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'securesubmitcert@e-hps.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production'   => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Get your API keys from your TSYS TransIT account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
			),
			'merchant_id'     => array(
				'title'       => __( 'Live Merchant ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'user_id'         => array(
				'title'       => __( 'Live MultiPass User ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'password'        => array(
				'title'       => __( 'Live Password', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'device_id'       => array(
				'title'       => __( 'Live Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'tsep_device_id'       => array(
				'title'       => __( 'Live TSEP Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'transaction_key' => array(
				'title'       => __( 'Live Transaction Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'sandbox_merchant_id'     => array(
				'title'       => __( 'Sandbox Merchant ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_user_id'         => array(
				'title'       => __( 'Sandbox MultiPass User ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_password'        => array(
				'title'       => __( 'Sandbox Password', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_device_id'       => array(
				'title'       => __( 'Sandbox Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_tsep_device_id'       => array(
				'title'       => __( 'Sandbox TSEP Device ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_transaction_key' => array(
				'title'       => __( 'Sandbox Transaction Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'debug' => array(
				'title'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Log all requests to and from the gateway. This can also log private data and should only be enabled in a development or stage environment.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
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

		$prefix = ( wc_string_to_bool( $settings['is_production'] ) ) ? '' : 'sandbox_';
		if ( ! empty( $settings[$prefix . 'user_id'] ) && ! empty( $settings[$prefix . 'password'] ) ) {
			try {
				$this->configure_merchant_settings();
				$settings[$prefix . 'transaction_key']  = $this->create_transaction_key();
			} catch ( \Exception $e ) {
				$settings[$prefix . 'transaction_key'] = '';
				add_action( 'admin_notices', function() {
					echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>'
                        . esc_html__(
                            'Invalid MultiPass User ID or Password. Please try again.',
                            'globalpayments-gateway-provider-for-woocommerce' )
                        . '</strong></p></div>';
				});
			}
		}
		$settings[$prefix . 'user_id']  = null;
		$settings[$prefix . 'password'] = null;

		update_option( $option_name, $settings );
	}

	public function get_frontend_gateway_options() {
		return array(
			'deviceId' => $this->get_credential_setting( 'tsep_device_id' ),
			'manifest' => $this->create_manifest(),
			'env'      => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'merchantId'     => $this->get_credential_setting( 'merchant_id' ),
			'username'       => $this->get_credential_setting( 'user_id' ), // only needed to create transation key
			'password'       => $this->get_credential_setting( 'password' ), // only needed to create transation key
			'transactionKey' => $this->get_credential_setting( 'transaction_key' ),
			'tsepDeviceId'	 => $this->get_credential_setting( 'tsep_device_id' ),
			'deviceId'       => $this->get_credential_setting( 'device_id' ),
			'developerId'    => '003226G004', // provided during certification
			'environment'    => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
			'debug'          => $this->debug,
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
}
