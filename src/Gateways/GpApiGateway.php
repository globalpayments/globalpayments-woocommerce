<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\{Environment, GatewayProvider, Channel};
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\DiUiApms\{BankSelect, Blik};
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm\Paypal;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure\CheckEnrollmentRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\{PayOrderTrait, HppTrait};
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\{ApplePay, ClickToPay, GooglePay};
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\{Clearpay, Klarna};
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking\OpenBanking;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class GpApiGateway extends AbstractGateway {
	use PayOrderTrait, HppTrait;
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

	/**
	 * Payment Interface Mode
	 *
	 * @var string
	 */
	public $payment_interface;

	/**
	 * Enable BLIK for HPP
	 *
	 * @var string
	 */
	public $enable_blik_hpp;

	/**
	 * Enable Open Banking for HPP
	 *
	 * @var string
	 */
	public $enable_open_banking_hpp;

	/**
	 * Enable Google Pay for HPP
	 *
	 * @var string
	 */
	public $enable_gpay_hpp;

	/**
	 * Enable Apple Pay for HPP
	 *
	 * @var string
	 */
	public $enable_applepay_hpp;

	/**
	 * Custom text for HPP payment method display
	 *
	 * @var string
	 */
	public $hpp_text;

	protected static string $js_lib_version = '4.1.17';

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );

		array_push( $this->supports, 'globalpayments_hosted_fields', 'globalpayments_three_d_secure' );

		// Start HPP functionality if configured for HPP mode
		if ( ! $is_provider && $this->is_hpp_mode() ) {
			$this->init_hpp();
		}
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
		$theArray = array(
			'is_production'        => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					/* translators: %s: Email address of support team */
					esc_html__(
						'Get your App ID and App Key from your %sGlobal Payments Developer Account%s. Please follow the instructions provided in the %splugin description%s. When you are ready to go Live, please contact %ssupport to get you live credentials.',
						'globalpayments-gateway-provider-for-woocommerce'
					),
					'<a href="https://developer.globalpay.com/user/register" target="_blank">',
					'</a>',
					'<a href="https://wordpress.org/plugins/global-payments-woocommerce/" target="_blank">',
					'</a>',
					'<a href="mailto:' . $this->get_first_line_support_email() . '?Subject=WooCommerce Live Credentials">',
					'</a>'
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
				'title'       => __( '', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class'       => 'live-toggle',
			),
            'account_name_dropdown'         => array(
                'title'       => __( 'Live Account Name*', 'globalpayments-gateway-provider-for-woocommerce' ),
                'type'        => 'select',
                'default'     => '',
                'default'     => '',
                'class'       => 'required live-toggle',
                'description' => __( 'Select which account to use when processing a transaction. Default account will be used if this is not specified. <br>For assistance locating your account name, please contact our <a href="https://developer.globalpay.com/support/integration-support" arget="_blank">Integration Support</a> Team based on location', 'globalpayments-gateway-provider-for-woocommerce' ),
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
				'type'        => 'text',
				'default'     => '',
				'class'       => 'sandbox-toggle',
			),
            'sandbox_account_name_dropdown'  => array(
                'title'       => __( 'Sandbox Account Name*', 'globalpayments-gateway-provider-for-woocommerce' ),
                'type'        => 'select',
                'default'     => '',
                'default'     => '',
                'class'       => 'required sandbox-toggle',
                'description' => __( 'Select which account to use when processing a transaction. Default account will be used if this is not specified. <br>For assistance locating your account name, please contact our <a href="https://developer.globalpay.com/support/integration-support" arget="_blank">Integration Support</a> Team based on location', 'globalpayments-gateway-provider-for-woocommerce' ),
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
			'payment_interface' => array(
				'title'       => __( 'Payment Interface', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose how customers will enter their payment details. Drop-in UI provides card fields directly on your checkout page. Hosted Payment Pages redirect customers to a secure payment form.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'drop_in',
				'options'     => array(
					'drop_in' => __( 'Drop-in UI', 'globalpayments-gateway-provider-for-woocommerce' ),
					'hpp'     => __( 'Hosted Payment Pages', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
			'section_hpp'      => array(
				'title' => __( 'Hosted Payment Settings', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'  => 'title',
			),
			'hpp_text' => array(
				'title'       => __( 'Hosted Payment Pages Display Text', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Custom text to display on the checkout page when Hosted Payment Pages is selected. If left empty, a default message will be shown.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => __( 'Select Place Order to view available payment methods.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'css'         => 'width: 400px; height: 75px;',
				'desc_tip'    => true,
			),
			'enable_gpay_hpp' => array(
				'title'       => __( 'Enable Google Pay for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Google Pay for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Google Pay as a payment option on the Hosted Payment Page.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
			),
			'enable_applepay_hpp' => array(
				'title'       => __( 'Enable Apple Pay for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Apple Pay for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Apple Pay as a payment option on the Hosted Payment Page.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
			),
		);

		// Create these configuration options only if proper country and currency are configured
		if (
			WC()->countries->get_base_country() === 'PL'
			&& get_woocommerce_currency() === 'PLN'
		) {
		$theArray =	array_merge(
				$theArray,
				array(
					'enable_blik_hpp' => array(
						'title'       => __( 'Enable BLIK for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
						'label'       => __( 'Enable BLIK for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
						'type'        => 'checkbox',
						'description' => __( 'Enable BLIK as a payment option on the Hosted Payment Page.', 'globalpayments-gateway-provider-for-woocommerce' ),
						'default'     => 'no',
					),
					'enable_open_banking_hpp' => array(
						'title'       => __( 'Enable Open Banking for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
						'label'       => __( 'Enable Open Banking for HPP', 'globalpayments-gateway-provider-for-woocommerce' ),
						'type'        => 'checkbox',
						'description' => __( 'Enable Open Banking as a payment option on the Hosted Payment Page.', 'globalpayments-gateway-provider-for-woocommerce' ),
						'default'     => 'no',
					),
				)
			);
		}

		$theArray = array_merge(
			$theArray,
			array(
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
					'default' => 'yes',
					'description'       => __($this->get_three_d_secure_display_text(), 'globalpayments-gateway-provider-for-woocommerce'),
					'custom_attributes' => $this->three_d_secure_required() ? ["disabled" => true] : []
				),
			)
		);

		return $theArray;
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
		
		$options = array(
			'accessToken'           => $this->get_access_token(),
			'apiVersion'            => GpApiConnector::GP_API_VERSION,
			'env'                   => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
			'requireCardHolderName' => true,
			'enableThreeDSecure'    => $this->enable_three_d_secure,
			'fieldValidation' => [
				'enabled' => true,
			],
			'language' => substr( get_user_locale(), 0, 2 ),
			'payment_interface'     => $this->payment_interface,
		);

		// For HPP, add the nonce
		if ( $this->is_hpp_mode() ) {
			$options['hpp_nonce'] = wp_create_nonce( 'gp_hpp_nonce' );
			$options['payment_interface'] = 'hpp';
			$options['hpp_text'] = $this->get_option( 'hpp_text' );
			// Remove drop-in UI specific options that might interfere
			unset( $options['accessToken'] );
			unset( $options['apiVersion'] );
			unset( $options['fieldValidation'] );
		}

		return $options;
	}

	/**
	 * Ensures the notification URL is valid and HTTPS, even in local/test environments.
	 *
	 * @param string $url
	 * @return string
	 */
	private function get_valid_notification_url( string $url ) : string
	{
		// Replace localhost/127.0.0.1 with a sandbox placeholder for test environments
		if ( !$this->is_production ) {
			if (strpos( $url, 'localhost' ) !== false || strpos( $url, '127.0.0.1' ) !== false ) {
				$url = str_replace( ['localhost', '127.0.0.1'], 'sandbox-webhook.example.com', $url ) ;
				$url = str_replace( 'http://', 'https://', $url );
			}
		}
		// Ensure HTTPS
		if ( strpos( $url, 'http://' ) === 0 ) {
			$url = 'https://' . substr( $url, 7 );
		}
		// Validate URL
		if ( !filter_var( $url, FILTER_VALIDATE_URL ) ) {
			// Fallback to a basic valid URL format
			$site_url = get_site_url();
			$url = $site_url . '/wc-api/' . basename( $url );
		}
		return $url;
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
			'methodNotificationUrl'    => $this->get_valid_notification_url(
				WC()->api_request_url( 'globalpayments_threedsecure_methodnotification' )
			),
			'challengeNotificationUrl' => $this->get_valid_notification_url(
				WC()->api_request_url( 'globalpayments_threedsecure_challengenotification' )
			),
			'merchantContactUrl'       => $this->merchant_contact_url,
			'dynamicHeaders'           => [
				'x-gp-platform'  => 'wordpress;version=' . $wp_version . ';woocommerce;version=' . WC()->version,
				'x-gp-extension' => 'globalpayments-woocommerce;version=' . Plugin::VERSION,
			],
			'debug'                    => $this->debug,
			'enable_gpay_hpp'		   => $this->get_option('enable_gpay_hpp'),
			'enable_applepay_hpp'	   => $this->get_option('enable_applepay_hpp'),
			'enable_blik_hpp'		   => $this->get_option('enable_blik_hpp'),
			'enable_open_banking_hpp'  => $this->get_option('enable_open_banking_hpp'),
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

	/**
	 * Override perents payment fields method to handle HPP mode
	 */
	public function payment_fields() {

		if ( isset( $this->payment_interface ) && $this->payment_interface === 'hpp' ) {

			// For WooCommerce classic checkout mode, display custom text and add nonce field
			echo '<div class="globalpayments-hpp-info globalpayments-hpp-mode">';
			//Below doesn't work as the perent class has $this->id !== 'globalpayments_gpapi'
			// echo $this->environment_indicator();
			$hpp_text = $this->get_option( 'hpp_text' );
			if ( ! empty( $hpp_text ) ) {
				echo '<p>' . wp_kses_post( nl2br( esc_html( $hpp_text ) ) ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'Pay With Credit / Debit Card Via Globalpayments', 'globalpayments-gateway-provider-for-woocommerce' ) . '</p>';
			}
			
			echo '</div>';

			// Add hidden nonce field for HPP
			echo '<input type="hidden" name="gp_hpp_nonce" value="' . wp_create_nonce( 'gp_hpp_payment' ) . '" />';

			// Defult WooCommerce checkout validation will still occour
			echo '<input type="hidden" name="' . esc_attr( $this->id ) . '[checkout_validated]" value="1" />';
			
			return;
		}
		
		// Invoke Default behavior for drop-in UI
		parent::payment_fields();
	}

	public function secure_payment_fields_config() {
		// Only return secure payment fields config for drop-in UI mode
		if ( $this->is_hpp_mode() ) {
			return array();
		}
		return parent::secure_payment_fields_config();
	}

	public function secure_payment_fields() {
		// Only return secure payment fields for drop-in UI
		if ( $this->is_hpp_mode() ) {
			return array();
		}

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

	public function secure_payment_fields_styles() {
		// Only return secure payment fields styles for drop-in UI
		if ( $this->is_hpp_mode() ) {
			// No Styles needed for HPP
			return array();
		}
		return parent::secure_payment_fields_styles();
	}

	public function getThreedsecureFields() {
		if ( $this->is_hpp_mode() ) {
			// No 3DS fields needed for HPP
			return null;
		}
		return parent::getThreedsecureFields();
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

		if ( $this->enable_blik === "yes" ) {
			// Add BLIK payment status callback handler
			add_action(
				'woocommerce_api_globalpayments_blik_status_handler',
				array( Blik::class, 'handle_gpapi_apm_status_notification' )
			);

			// Add BLIK payment redirect/response callback handler
			add_action(
				'woocommerce_api_globalpayments_blik_redirect_handler',
				array( Blik::class, 'handle_gpapi_apm_success_redirect' )
			);
		}

		if ( $this->enable_bank_select === "yes" ) {
			// Add bank_select payment status callback handler
			add_action(
				'woocommerce_api_globalpayments_bank_select_status_handler',
				array( BankSelect::class, 'handle_gpapi_apm_status_notification' )
			);

			// Add bank_select payment redirect/response callback handler
			add_action(
				'woocommerce_api_globalpayments_bank_select_redirect_handler',
				array( BankSelect::class, 'handle_gpapi_apm_success_redirect' )
			);
		}
		
}

	public function woocommerce_globalpayments_gpapi_settings( $settings ) {
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return $settings;
		}
		// Prevent the user from disabling 3DS option if required in their country, only applicable in production mode
		if( !wc_string_to_bool( $settings['enable_three_d_secure'] ) && self::three_d_secure_required() ) {
			$settings['enable_three_d_secure'] = "yes";
		}
		if ( wc_string_to_bool( $settings['is_production'] ) ) {
			if ( empty( $settings['app_id'] ) || empty( $settings['app_key'] ) ) {
				add_action( 'admin_notices', function () {
					// esc_html__ is used here to sanitize user input and prevent XSS vulnerabilities
					echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' .
					     esc_html__( 'Please provide Live Credentials.', 'globalpayments-gateway-provider-for-woocommerce' ) . '</strong></p></div>';
				} );
			}

			return $settings;
		}
		if ( empty( $settings['sandbox_app_id'] ) || empty( $settings['sandbox_app_key'] ) ) {
			add_action( 'admin_notices', function () {
				// esc_html__ is used here to sanitize user input and prevent XSS vulnerabilities
				echo '<div id="message" class="notice notice-error is-dismissible"><p><strong>' .
				     esc_html__( 'Please provide Sandbox Credentials.', 'globalpayments-gateway-provider-for-woocommerce' ) . '</strong></p></div>';
			} );
		}

		return $settings;
	}

	public function after_checkout_validation( $data, $errors ) {
		if ( $this->id !== $data['payment_method'] ) {
			return;
		}


		if ( ! empty( $errors->errors ) || !$this->enable_three_d_secure ) {
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
		$response                   = wp_json_encode( [
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

				$response = wp_json_encode( [
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

	 /**
     * Determines if 3DS is required, based on store location
     * 
     * @return bool
     */
    private function three_d_secure_required()
    {
        //Note: cannot access class varables from get_gateway_form_fields method.
        if ( isset( $this->is_production ) && false == $this->is_production ) {
            return false;
        } else {
            $plugin_settings = get_option( 'woocommerce_' . self::GATEWAY_ID . '_settings', array() );
            if( isset( $plugin_settings["is_production"] ) && false === wc_string_to_bool( $plugin_settings["is_production"] ) 
                || isset( $plugin_settings["enabled"] ) && false === wc_string_to_bool( $plugin_settings["enabled"] ) ) {
                return false;
            }
        }

        $three_d_secure_required_countries = array(
			"AT","BE","BG","HR","CY","CZ","DK","EE","FI","FR","DE","GR","HU",
			"IE","IT","LV","LT","LU","MT","NL","PL","PT","RO","SK","SI","ES",
			"SE","EU","IS","LI","NO","CH","AL","BA","MD","ME","MK","RS","TR",
			"UA","AD","BY","MC","RU","SM","GB","VA", "JP", "IN"
        );
		
        return in_array( WC()->countries->get_base_country(), $three_d_secure_required_countries );
    }
    /**
     * Returns display text for 3DS option
     */
    private function get_three_d_secure_display_text()
    {
        return sprintf(
            "Based on the WooCommerce store location 3D Secure %s", ( self::three_d_secure_required() ) ? 
            "is required for all tranactions, when in live mode" : 
            "is not required"
        );
    }

	/**
	 * Configures GpApi gateway options
	 *
	 * @return
	 */
	public function init_form_fields()
	{
		parent::init_form_fields();
		if (
			WC()->countries->get_base_country() === 'PL'
			&& get_woocommerce_currency() === 'PLN'
		) {
			$this->form_fields = array_merge(
				$this->form_fields,
				array(
					'enable_blik'     => array(
						'title'       => __( 'Enable Blik Payment', 'globalpayments-gateway-provider-for-woocommerce' ),
						'label'       => __( 'Enable Blik Payment', 'globalpayments-gateway-provider-for-woocommerce' ),
						'type'        => 'checkbox',
						'default'     => ''
					),
					'enable_bank_select'     => array(
						'title'       => __( 'Enable Open Banking Payment', 'globalpayments-gateway-provider-for-woocommerce' ),
						'label'       => __( 'Enable Open Banking Payment', 'globalpayments-gateway-provider-for-woocommerce' ),
						'type'        => 'checkbox',
						'default'     => ''
					),
				)
			);
		}
	}

	/**
	 * Use DiUi handler or abstract method
	 *
	 * @param int $order_id
	 *
	 * @return array
	 * @throws ApiException
	 */
	public function process_payment( $order_id ) {
		if ( !empty( $_POST["blik-payment"] ) && $_POST["blik-payment"] === "1" )
			return Blik::process_blik_sale( $this, $order_id );
		if ( !empty( $_POST["open_banking"] ) )
			return BankSelect::process_bank_select_sale( $this, $order_id );

		// Handle Hosted Payment Pages process payment using the HppTrait
		if ( $this->is_hpp_mode() ) {
			return $this->process_hpp_payment( $order_id );
		}

		return parent::process_payment( $order_id );
	}

	/**
	 * Override helper params to provide payment interface data
	 *
	 * @return array
	 */
	public function get_helper_params() {
		$params = parent::get_helper_params();

		// Add payment interface
		$params['payment_interface'] = $this->payment_interface ?? 'drop_in';

		// Add HPP nonce and text
		if ( $this->payment_interface === 'hpp' ) {
			$params['hpp_nonce'] = wp_create_nonce( 'gp_hpp_payment' );
			$params['hpp_text'] = $this->get_option( 'hpp_text' );
			$params['enableThreeDSecure'] = $this->enable_three_d_secure;
		}

		if ( $this->payment_interface === 'hpp' ) {
			$toggle_array = array_filter( $params['toggle'], function( $gateway_id ) {
				return $gateway_id !== $this->id;
			} );
			$params['toggle'] = array_values( $toggle_array ); // Re-index array
		}

		return $params;
	}

	 /* Used for handling AVS/CVN response codes
	 *
	 * @param Transaction $response
	 *
	 * @return void
	 */
	protected function handle_avs_cvn_response_codes( Transaction $response ) {
		$response->avsResponseCode = $response->cardIssuerResponse->avsAddressResult;
		$response->cvnResponseCode = $response->cardIssuerResponse->cvvResult;
	}

	/**
	 * Check if the gateway payment_interface is HPP
	 *
	 * @return bool
	 */
	public function is_hpp_mode(): bool {
		return isset( $this->payment_interface ) && $this->payment_interface === 'hpp';
	}


}
