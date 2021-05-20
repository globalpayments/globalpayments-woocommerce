<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\GpApi\Channels;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class GpApiGateway extends AbstractGateway {
	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_gpapi';
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
		$this->id                 = self::GATEWAY_ID;
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
			'apiVersion'  => GpApiConnector::GP_API_VERSION,
			'env'         => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'appId'                    => $this->app_id,
			'appKey'                   => $this->app_key,
			'channel'                  => Channels::CardNotPresent,
			'country'                  => wc_get_base_location()['country'],
			'developerId'              => '',
			'environment'              => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
			'methodNotificationUrl'    => WC()->api_request_url('globalpayments_threedsecure_methodnotification'),
			'challengeNotificationUrl' => WC()->api_request_url('globalpayments_threedsecure_challengenotification'),
		);
	}

	protected function get_access_token() {
		try {
			$request  = $this->prepare_request( self::TXN_TYPE_GET_ACCESS_TOKEN );
			$response = $this->submit_request( $request );

			return $response->token;
		} catch (\Exception $e) {
			return null;
		}
	}

	protected function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'after_checkout_validation' ), 10, 2 );

		/**
		 * The WooCommerce API allows plugins make a callback to a special URL that will then load the specified class (if it exists)
		 * and run an action. This is also useful for gateways that are not initialized.
		 */
		add_action( 'woocommerce_api_globalpayments_threedsecure_checkenrollment', array( $this, 'process_threeDSecure_checkEnrollment' ) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_methodnotification', array( $this, 'process_threeDSecure_methodNotification' ) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_initiateauthentication', array( $this, 'process_threeDSecure_initiateAuthentication' ) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_challengenotification', array( $this, 'process_threeDSecure_challengeNotification' ) );
	}

	public function after_checkout_validation( $data, $errors ) {
		if ( ! empty( $errors->errors ) ) {
			return;
		}
		if ( $this->id !== $data['payment_method'] ) {
			return;
		}
		$post_data = $this->get_post_data();
		if ( isset( $post_data[ $this->id ]['checkout_validated'] ) && 1 == $post_data[ $this->id ]['checkout_validated'] ) {
			return;
		}

		wc_add_notice( $this->id . '_checkout_validated', 'error', array( 'id' => $this->id ) );
	}

	public function mapResponseCodeToFriendlyMessage( $responseCode ) {
		if ( 'DECLINED' === $responseCode ) {
			return __( 'Your card has been declined by the bank.', 'globalpayments-gateway-provider-for-woocommerce' );
		}

		return __( 'An error occurred while processing the card.', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function process_threeDSecure_checkEnrollment() {
		$request = $this->prepare_request( parent::TXN_TYPE_CHECK_ENROLLMENT );
		$this->client->submit_request( $request );
	}

	public function process_threeDSecure_initiateAuthentication() {
		$request = $this->prepare_request( parent::TXN_TYPE_INITIATE_AUTHENTICATION );
		$this->client->submit_request( $request );
	}

	public function process_threeDSecure_methodNotification() {
		if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}
		if ( 'application/x-www-form-urlencoded' !== $_SERVER['CONTENT_TYPE'] ) {
			return;
		}

		$convertedThreeDSMethodData = wc_clean( json_decode( base64_decode( $_POST['threeDSMethodData'] ) ) );
		$response = json_encode([
			'threeDSServerTransID' => $convertedThreeDSMethodData->threeDSServerTransID,
		]);

		wp_enqueue_script(
			'globalpayments-threedsecure-lib',
			Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
			. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js'
		);
		wp_add_inline_script( 'globalpayments-threedsecure-lib', 'GlobalPayments.ThreeDSecure.handleMethodNotification(' . $response . ');' );
		wp_print_scripts();
		exit();
	}

	public function process_threeDSecure_challengeNotification() {
		if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}
		if ( 'application/x-www-form-urlencoded' !== $_SERVER['CONTENT_TYPE'] ) {
			return;
		}

		try {
			$response = new \stdClass();

			if ( isset( $_POST['cres'] ) ) {
				$convertedCRes = wc_clean( json_decode( base64_decode( $_POST['cres'] ) ) );

				$response = json_encode([
					'threeDSServerTransID' => $convertedCRes->threeDSServerTransID,
					'transStatus'          => $convertedCRes->transStatus ?? '',
				]);
			}

			if ( isset( $_POST['PaRes'] ) ) {
				$response = json_encode( [
					'MD'    => wc_clean( $_POST['MD'] ),
					'PaRes' => wc_clean( $_POST['PaRes'] ),
				], JSON_UNESCAPED_SLASHES );
			}
			wp_enqueue_script(
				'globalpayments-threedsecure-lib',
				Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
				. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js'
			);
			wp_add_inline_script( 'globalpayments-threedsecure-lib', 'GlobalPayments.ThreeDSecure.handleChallengeNotification(' . $response . ');' );
			wp_print_scripts();
			exit();

		} catch (Exception $e) {
			$response = array('error' => TRUE, 'message' => $e->getMessage());
		}
	}

	protected function get_session_amount() {
		$cart_totals = WC()->session->get('cart_totals');
		return round($cart_totals['total'], 2);
	}

	protected function get_customer_email() {
		return WC()->customer->get_billing_email();
	}

	protected function get_billing_address() {
		return [
			'streetAddress1' => WC()->customer->get_billing_address_1(),
			'streetAddress2' => WC()->customer->get_billing_address_2(),
			'city'           => WC()->customer->get_billing_city(),
			'state'          => WC()->customer->get_billing_state(),
			'postalCode'     => WC()->customer->get_billing_postcode(),
			'country'        => WC()->customer->get_billing_country(),
			'countryCode'    => '',
		];
	}

	protected function get_shipping_address() {
		return [
			'streetAddress1' => WC()->customer->get_shipping_address_1(),
			'streetAddress2' => WC()->customer->get_shipping_address_2(),
			'city'           => WC()->customer->get_shipping_city(),
			'state'          => WC()->customer->get_shipping_state(),
			'postalCode'     => WC()->customer->get_shipping_postcode(),
			'country'        => WC()->customer->get_shipping_country(),
			'countryCode'    => '',
		];
	}
}
