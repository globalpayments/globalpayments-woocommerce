<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm\Paypal;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure\CheckEnrollmentRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\PayOrderTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ClickToPay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\GooglePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking\OpenBanking;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class GpApiGateway extends AbstractGateway {
	use PayOrderTrait;
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
	 * Live App ID
	 *
	 * @var string
	 */
	public $app_id;

	/**
	 * Live App Key
	 *
	 *
	 * @var string
	 */
	public $app_key;

	/**
	 * Live Account Name
	 *
	 *
	 * @var string
	 */
	public string $account_name;

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
	 * Sandbox Account Name
	 *
	 *
	 * @var string
	 */
	public string $sandbox_account_name;

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
	 * Transaction descriptor length
	 *
	 * @var int
	 */
	public $txn_descriptor_length = 25;

	/**
	 * Integration's Developer ID
	 *
	 * @var string
	 */
	public $developer_id = '';

	/**
	 * Should debug
	 *
	 * @var bool
	 */
	public $debug;

	/**
	 * Enable/Disable threeDSecure
	 *
	 * @var bool
	 */
	public $enable_three_d_secure;

	protected static string $js_lib_version = '4.0.15';

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields', 'globalpayments_three_d_secure' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Unified Payments', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'api.integrations@globalpay.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production'        => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
				/* translators: %s: Email address of support team */
					__( 'Get your App Id and App Key from your <a href="https://developer.globalpay.com/user/register" target="_blank">Global Payments Developer Account</a>. ' .
					    'Please follow the instructions provided in the <a href="https://wordpress.org/plugins/global-payments-woocommerce/" target="_blank">plugin description</a>.<br/>' .
					    'When you are ready for Live, please contact <a href="mailto:%s?Subject=WooCommerce%%20Live%%20Credentials">support</a> to get you live credentials.',
						'globalpayments-gateway-provider-for-woocommerce'
					),
					$this->get_first_line_support_email()
				),
				'default'     => 'no',
			),
			'app_id'               => array(
				'title' => __( 'Live App Id*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'text',
				'class' => 'required live-toggle',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'app_key'              => array(
				'title' => __( 'Live App Key*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'password',
				'class' => 'required live-toggle',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'account_name'         => array(
				'title'       => __( 'Account Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class'       => 'live-toggle',
				'description' => __( 'Specify which account to use when processing a transaction. Default account will be used if this is not specified.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'sandbox_app_id'       => array(
				'title'   => __( 'Sandbox App Id*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'required sandbox-toggle',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'sandbox_app_key'      => array(
				'title'   => __( 'Sandbox App Key*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'class' => 'required sandbox-toggle',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'sandbox_account_name'  => array(
				'title'       => __( 'Sandbox Account Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class'       => 'sandbox-toggle',
				'description' => __( 'Specify which account to use when processing a transaction. Default account will be used if this is not specified.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'credentials_api_check'	=> array(
				'title'       => __( 'Credentials check', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Credentials check', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'button',
				'class'       => 'button-credentials-check button-primary',
				'default'     => __( 'Credentials check', 'globalpayments-gateway-provider-for-woocommerce' ),
				'description' => __( 'Note: The Payment Methods will not be displayed at checkout if the credentials are not correct.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'css'         => 'width: 200px',
			),
			'allow_card_saving'    => array(
				'title'       => __( 'Allow Card Saving', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Allow Card Saving', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
				/* translators: %s: Email address of support team */
					__( 'Note: to use the card saving feature, you must have multi-use token support enabled on your account. Please contact <a href="mailto:%s?Subject=WooCommerce%%20Card%%20Saving%%20Option">support</a> with any questions regarding this option.', 'globalpayments-gateway-provider-for-woocommerce' ),
					$this->get_first_line_support_email()
				),
				'default'     => 'no',
			),
			'section_general'      => array(
				'title' => __( 'General Settings', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'title',
			),
			'debug'                => array(
				'title'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Log all requests to and from the gateway. This can also log private data and should only be enabled in a development or stage environment.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'merchant_contact_url' => array(
				'title'             => __( 'Contact Url*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'desc_tip'          => true,
				'description'       => __( 'A link to an About or Contact page on your website with customer care information (maxLength: 256).', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
					'maxlength' => '256'
				),
			),
			'enable_three_d_secure' => array(
				'title'   => __( 'Enable 3D Secure', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'   => __( 'Enable 3D Secure', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes'
			),
		);
	}

	public function needs_setup() {
		if ( empty( $this->merchant_contact_url ) ) {
			return true;
		}
		if ( $this->is_production ) {
			return ( empty( $this->app_id ) || empty( $this->app_key ) );
		}

		return ( empty( $this->sandbox_app_id ) || empty( $this->sandbox_app_key ) );
	}

	public function get_frontend_gateway_options() {
		return array(
			'accessToken'           => $this->get_access_token(),
			'apiVersion'            => GpApiConnector::GP_API_VERSION,
			'env'                   => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
			'requireCardHolderName' => true,
			'enableThreeDSecure'    => $this->enable_three_d_secure,
			'fieldValidation' => [
				'enabled' => true,
			],
			'language' => substr( get_user_locale(), 0, 2 ),
		);
	}

	public function get_backend_gateway_options() {
		global $wp_version;

		return array(
			'appId'                    => $this->get_credential_setting( 'app_id' ),
			'appKey'                   => $this->get_credential_setting( 'app_key' ),
			'accountName'              => $this->get_credential_setting( 'account_name' ),
			'channel'                  => Channel::CardNotPresent,
			'country'                  => wc_get_base_location()['country'],
			'developerId'              => '',
			'environment'              => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
			'methodNotificationUrl'    => WC()->api_request_url( 'globalpayments_threedsecure_methodnotification' ),
			'challengeNotificationUrl' => WC()->api_request_url( 'globalpayments_threedsecure_challengenotification' ),
			'merchantContactUrl'       => $this->merchant_contact_url,
			'dynamicHeaders'           => [
				'x-gp-platform'  => 'wordpress;version=' . $wp_version . ';woocommerce;version=' . WC()->version,
				'x-gp-extension' => 'globalpayments-woocommerce;version=' . Plugin::VERSION,
			],
			'debug'                    => $this->debug,
		);
	}

	public function get_access_token() {
		$request  = $this->prepare_request( self::TXN_TYPE_GET_ACCESS_TOKEN );
		$response = $this->submit_request( $request );

		return $response->token;
	}

	/**
	 * Get the session ID used until now for the current browsing session.
	 *
	 * @return string|NULL Session ID, or NULL if unknown.
	 */
	private function get_transient_name() {
		if ( ! isset( WC()->session ) ) {
			return null;
		}
		if ( ! WC()->session->has_session() ) {
			return null;
		}

		return $this->id . '_' . WC()->session->get_session_cookie()[0];
	}

	public function secure_payment_fields() {
		$fields = parent::secure_payment_fields();
		$fields['card-holder-name-field'] = array(
			'class'       => 'card-holder-name',
			'label'       => esc_html__( 'Card Holder Name', 'globalpayments-gateway-provider-for-woocommerce' ),
			'placeholder' => esc_html__( 'Jane Smith', 'globalpayments-gateway-provider-for-woocommerce' ),
			'messages'    => array(
				'validation' => esc_html__( 'Please enter a valid Card Holder Name', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
		);
		return $fields;
	}

	protected function add_hooks() {
		parent::add_hooks();

		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			// Admin Pay for Order hooks
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'pay_order_modal' ), 99 );
			add_filter( 'globalpayments_secure_payment_fields_styles', array( $this, 'pay_order_modal_secure_payment_fields_styles' ) );
		}
		add_action( 'woocommerce_api_globalpayments_pay_order', array( $this, 'pay_order_modal_process_payment' ), 99 );
		add_action( 'woocommerce_api_globalpayments_get_payment_methods', array( $this, 'pay_order_modal_get_payment_methods' ) );
		add_filter( 'pre_update_option_woocommerce_globalpayments_gpapi_settings', array(
			$this,
			'woocommerce_globalpayments_gpapi_settings'
		) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'after_checkout_validation' ), 99, 2 );

		/**
		 * The WooCommerce API allows plugins make a callback to a special URL that will then load the specified class (if it exists)
		 * and run an action. This is also useful for gateways that are not initialized.
		 */
		add_action( 'woocommerce_api_globalpayments_threedsecure_checkenrollment', array(
			$this,
			'process_threeDSecure_checkEnrollment'
		) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_methodnotification', array(
			$this,
			'process_threeDSecure_methodNotification'
		) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_initiateauthentication', array(
			$this,
			'process_threeDSecure_initiateAuthentication'
		) );
		add_action( 'woocommerce_api_globalpayments_threedsecure_challengenotification', array(
			$this,
			'process_threeDSecure_challengeNotification'
		) );
		add_action( 'woocommerce_order_actions', array( $this, 'handle_adding_capture_order_action' ) );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
			$this,
			'renew_subscription'
		), 10, 3 );
		// When subscription is canceled remove the meta data
		add_action( 'woocommerce_subscription_status_pending-cancel_to_cancelled', function( $subscription ) {
			if ( $subscription->get_payment_method() === $this->id ) {
				$original_order = wc_get_order( $subscription->get_parent_id() );
					if( $original_order->get_meta( "_GP_multi_use_token" ) ) {
						$original_order->delete_meta_data( '_GP_multi_use_token' );
						$original_order->save();
					} else {
						return;
					}
			} else {
				return;
			}
		} );
	}

	public function woocommerce_globalpayments_gpapi_settings( $settings ) {
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return $settings;
		}
		if ( wc_string_to_bool( $settings['is_production'] ) ) {
			if ( empty( $settings['app_id'] ) || empty( $settings['app_key'] ) ) {
				add_action( 'admin_notices', function () {
					echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' .
					     __( 'Please provide Live Credentials.', 'globalpayments-gateway-provider-for-woocommerce' ) . '</strong></p></div>';
				} );
			}

			return $settings;
		}
		if ( empty( $settings['sandbox_app_id'] ) || empty( $settings['sandbox_app_key'] ) ) {
			add_action( 'admin_notices', function () {
				echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' .
				     __( 'Please provide Sandbox Credentials.', 'globalpayments-gateway-provider-for-woocommerce' ) . '</strong></p></div>';
			} );
		}

		return $settings;
	}

	public function after_checkout_validation( $data, $errors ) {
		if ( ! empty( $errors->errors ) || !$this->enable_three_d_secure ) {
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

	public function process_threeDSecure_checkEnrollment() {
		try {
			$request = $this->prepare_request( parent::TXN_TYPE_CHECK_ENROLLMENT );
			$this->client->submit_request( $request );
		} catch ( \Exception $e ) {
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
		} catch ( \Exception $e ) {
			wp_send_json( [
				'error'   => true,
				'message' => $e->getMessage(),
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
		$response                   = json_encode( [
			'threeDSServerTransID' => $convertedThreeDSMethodData->threeDSServerTransID,
		] );

		wp_enqueue_script(
			'globalpayments-threedsecure-lib',
			Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
			. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js'
		);
		wp_add_inline_script(
			'globalpayments-threedsecure-lib',
			'window.addEventListener(\'load\', function(){ GlobalPayments.ThreeDSecure.handleMethodNotification(' . $response . '); } )' );
		?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <?php wp_print_scripts( 'globalpayments-threedsecure-lib' ); ?>
</head>
<body>
</body>
</html>
		<?php
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

				$response = json_encode( [
					'threeDSServerTransID' => $convertedCRes->threeDSServerTransID,
					'transStatus'          => $convertedCRes->transStatus ?? '',
				] );
			}

			wp_enqueue_script(
				'globalpayments-threedsecure-lib',
				Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
				. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js'
			);
			wp_add_inline_script(
				'globalpayments-threedsecure-lib',
				'window.addEventListener(\'load\', function(){ GlobalPayments.ThreeDSecure.handleChallengeNotification(' . $response . '); } )' );
			?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <?php wp_print_scripts( 'globalpayments-threedsecure-lib' ); ?>
</head>
<body>
</body>
</html>
			<?php
			exit();
		} catch ( Exception $e ) {
			wp_send_json( [
				'error'   => true,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle adding capture functionality to the "Edit Order" screen
	 *
	 * @param array $actions
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function handle_adding_capture_order_action( $actions ) {
		global $theorder;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order_is_captured = $theorder->get_meta( '_globalpayments_payment_captured' );
		} else {
			$order_is_captured = get_post_meta( $theorder->get_id(), '_globalpayments_payment_captured', true );
		}

		if ( $order_is_captured === 'is_captured' || $this->payment_action === AbstractGateway::TXN_TYPE_SALE ) {
			return $actions;
		}

		$actions['capture_credit_card_authorization'] = __( 'Capture credit card authorization', 'globalpayments-gateway-provider-for-woocommerce' );

		return $actions;
	}

	/**
	 * Handles subscription renewals, gets the muti-use token from the order meta and charges it. 
	 * @return bool
	 */
	public function renew_subscription( $amount_to_charge, $renewal_order ) {
		$renewal_order_subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id(),array( "order_type"=>"parent") );
		$parent_order = false;
		foreach ( $renewal_order_subscriptions as $renewal_order_subscription ) {
			if ( $renewal_order_subscription->get_parent_id() ) {
				$parent_order = wc_get_order( $renewal_order_subscription->get_parent_id() );
			}
		}

		if ( !$parent_order || !$parent_order->get_meta( "_GP_multi_use_token" ) ) {

			return;
		}
		$request  = $this->prepare_request( parent::TXN_TYPE_SUBSCRIPTION_PAYMENT, $renewal_order, array( "multi_use_token"=> $parent_order->get_meta( "_GP_multi_use_token" ) ) );
		$response = $this->client->submit_request( $request );
		$client_trans_ref = $response->transactionReference->clientTransactionId;

		if ( parent::handle_response( $request, $response ) ) {
			$renewal_order->add_order_note( sprintf( __( "Subscription Renewal Successful \r\n Transaction Reference: %s", "globalpayments-gateway-provider-for-woocommerce" ), $client_trans_ref ) );
			$renewal_order->payment_complete();
			$renewal_order->save();

			return true;
		} else {
			$renewal_order->add_order_note( sprintf( __( "Subscription Renewal Payment Failed \r\nTransaction Reference: %s", "globalpayments-gateway-provider-for-woocommerce" ), $client_trans_ref ) );
			$renewal_order->save();

			return false;
		}

		return false;
	}
	/**
	 * Returns gateway supported payment methods.
	 *
	 * @return string[]
	 */
	public static function get_payment_methods() {
		return array(
			ClickToPay::class,
			GooglePay::class,
			ApplePay::class,
			Affirm::class,
			Clearpay::class,
			Klarna::class,
			OpenBanking::class,
			Paypal::class,
		);
	}
}
