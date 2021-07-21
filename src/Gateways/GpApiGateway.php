<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\GpApi\Channels;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure\CheckEnrollmentRequest;
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
	 * Sandbox App ID
	 *
	 * @var string
	 */
	public $sandbox_app_id;

	/**
	 * Sandbox App Key
	 *
	 *
	 * @var string
	 */
	public $sandbox_app_key;

	/**
	 * Production App ID
	 *
	 * @var string
	 */
	public $app_id;

	/**
	 * Production App Key
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
	 * Merchant Contact Url
	 *
	 * @var string
	 */
	public $merchant_contact_url;

	/**
	 * Integration's Developer ID
	 *
	 * @var string
	 */
	public $developer_id = '';

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Unified Commerce Platform', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Global Payments Unified Commerce Platform', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'api.integrations@globalpay.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'section_sandbox' => array(
				'title'       => __( 'Sandbox Credentials', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'title',
				'description' => __(
					'Get your App Id and App Key from your <a href="https://developer.globalpay.com/user/register" target="_blank">Global Payments Developer Account</a>. ' .
					'Please follow the instuctions provided in the <a href="https://wordpress.org/plugins/global-payments-woocommerce/" target="_blank">plugin description</a>.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
			),
			'sandbox_app_id' => array(
				'title'       => __( 'Sandbox App Id', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
			),
			'sandbox_app_key' => array(
				'title'       => __( 'Sandbox App Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'description' => '',
				'default'     => '',
			),
			'section_live' => array(
				'title'       => __( 'Live Credentials', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
				/* translators: %s: Email address of support team */
					__( 'When you are ready for Live, please contact <a href="mailto:%s?Subject=WooCommerce%%20Live%%20Credentials">support</a> to get you live credentials.',
						'globalpayments-gateway-provider-for-woocommerce' ),
					$this->get_first_line_support_email()
				),
			),
			'app_id' => array(
				'title'       => __( 'Live App Id', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
			),
			'app_key' => array(
				'title'       => __( 'Live App Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'description' => '',
				'default'     => '',
			),
			'is_production' => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'description' => __(
					'Allows you to switch between the Live and Sandbox version of your Global Payments account.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'default'     => 'no',
			),
			'section_general' => array(
				'title' => __( 'General Settings', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'title',
			),
			'merchant_contact_url' => array(
				'title'             => __( 'Contact Url*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'desc_tip'          => true,
				'description'       => __( 'A link to an About or Contact page on your website with customer care information (maxLength: 50).', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array( 'required' => 'required' ),
			),
		);
	}

	public function needs_setup() {
		if ( empty( $this->merchant_contact_url ) ) {
			return true;
		}
		if ( wc_string_to_bool( $this->is_production ) ) {
			return ( empty( $this->app_id ) || empty( $this->app_key ) );
		}
		return ( empty( $this->sandbox_app_id ) || empty( $this->sandbox_app_key ) );
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
			'appId'                    => $this->get_app_id(),
			'appKey'                   => $this->get_app_key(),
			'channel'                  => Channels::CardNotPresent,
			'country'                  => wc_get_base_location()['country'],
			'developerId'              => '',
			'environment'              => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
			'methodNotificationUrl'    => WC()->api_request_url('globalpayments_threedsecure_methodnotification'),
			'challengeNotificationUrl' => WC()->api_request_url('globalpayments_threedsecure_challengenotification'),
			'merchantContactUrl'       => $this->merchant_contact_url,
		);
	}

	protected function get_access_token() {
		$request  = $this->prepare_request( self::TXN_TYPE_GET_ACCESS_TOKEN );
		$response = $this->submit_request( $request );

		return $response->token;
	}

	protected function get_app_id() {
		return $this->is_production ? $this->app_id : $this->sandbox_app_id;
	}

	protected function get_app_key() {
		return $this->is_production ? $this->app_key : $this->sandbox_app_key;
	}

	protected function add_hooks() {
		parent::add_hooks();

		add_filter( 'pre_update_option_woocommerce_globalpayments_gpapi_settings', array( $this, 'woocommerce_globalpayments_gpapi_settings' ) );
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

	public function woocommerce_globalpayments_gpapi_settings( $settings ) {
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return $settings;
		}
		if ( empty( $settings['merchant_contact_url'] ) || 50 < strlen( $settings['merchant_contact_url'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' . __( 'Please provide a Contact Url (maxLength: 50). Gateway not enabled.' ) . '</strong></p></div>';
			});
			$settings['enabled'] = 'no';
		}
		if ( wc_string_to_bool( $settings['is_production'] ) ) {
			if ( empty( $settings['app_id'] ) || empty( $settings['app_key'] ) ) {
				add_action( 'admin_notices', function() {
					echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' . __( 'Please provide Live Credentials. Gateway not enabled.' ) . '</strong></p></div>';
				});
				$settings['enabled'] = 'no';
				return $settings;
			}
		}
		if ( empty( $settings['sandbox_app_id'] ) || empty( $settings['sandbox_app_key'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' . __( 'Please provide Sandbox Credentials. Gateway not enabled.' ) . '</strong></p></div>';
			});
			$settings['enabled'] = 'no';
		}
		return $settings;
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
		try {
			$request = $this->prepare_request( parent::TXN_TYPE_CHECK_ENROLLMENT );
			$this->client->submit_request( $request );
		} catch (\Exception $e) {
			wp_send_json( [
				'error'    => true,
				'message'  => $e->getMessage(),
				'enrolled' => CheckEnrollmentRequest::NO_RESPONSE,
			] );
		}
	}

	public function process_threeDSecure_initiateAuthentication() {
		try {
			$request = $this->prepare_request( parent::TXN_TYPE_INITIATE_AUTHENTICATION );
			$this->client->submit_request( $request );
		} catch (\Exception $e) {
			wp_send_json( [
				'error'    => true,
				'message'  => $e->getMessage(),
			] );
		}
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
			wp_send_json( [
				'error'    => true,
				'message'  => $e->getMessage(),
			] );
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
