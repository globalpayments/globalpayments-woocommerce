<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\CheckApiCredentialsTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm\Paypal;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\ClickToPay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets\GooglePay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking\OpenBanking;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Payment_Gateway_CC;
use WC_Order;
use GlobalPayments\Api\Entities\Transaction;

/**
 * Shared gateway method implementations
 */
abstract class AbstractGateway extends WC_Payment_Gateway_Cc {
	use CheckApiCredentialsTrait;
	/**
	 * Defines production environment
	 */
	const ENVIRONMENT_PRODUCTION = 'production';

	/**
	 * Defines sandbox environment
	 */
	const ENVIRONMENT_SANDBOX = 'sandbox';

	// auth requests
	const TXN_TYPE_AUTHORIZE      = 'authorize';
	const TXN_TYPE_BNPL_AUTHORIZE = 'bnpl_authorize';
	const TXN_TYPE_SALE           = 'charge';
	const TXN_TYPE_VERIFY         = 'verify';

	// dw requests
	const TXN_TYPE_DW_AUTHORIZATION = 'dw_authorization';

	const TXN_TYPE_OB_AUTHORIZATION = 'ob_authorization';

	// mgmt requests
	const TXN_TYPE_REFUND   = 'refund';
	const TXN_TYPE_REVERSAL = 'reverse';
	const TXN_TYPE_VOID     = 'void';
	const TXN_TYPE_CAPTURE  = 'capture';

	// transit requests
	const TXN_TYPE_CREATE_TRANSACTION_KEY = 'getTransactionKey';
	const TXN_TYPE_CREATE_MANIFEST        = 'createManifest';

	// report requests
	const TXN_TYPE_REPORT_TXN_DETAILS = 'transactionDetail';

	//gp-api requests
	const TXN_TYPE_GET_ACCESS_TOKEN = 'getAccessToken';

	//3DS requests
	const TXN_TYPE_CHECK_ENROLLMENT        = 'checkEnrollment';
	const TXN_TYPE_INITIATE_AUTHENTICATION = 'initiateAuthentication';

	const TXN_TYPE_PAYPAL_INITIATE = 'initiatePayment';
	// Subscription 
	const TXN_TYPE_SUBSCRIPTION_PAYMENT = 'subscriptionPayment';


	/**
	 * Gateway provider. Should be overriden by individual gateway implementations
	 *
	 * @var string
	 */
	public $gateway_provider;

	/**
	 * Payment method enabled status
	 *
	 * This should be a boolean value, but inner WooCommerce checks require
	 * this remain as a string value with possible options `yes` and `no`.
	 *
	 * @var string
	 */
	public $enabled;

	/**
	 * Payment method title shown to consumer
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Action to perform on checkout
	 *
	 * Possible actions:
	 *
	 * - `authorize` - authorize the card without auto capturing
	 * - `sale` - authorize the card with auto capturing
	 * - `verify` - verify the card without authorizing
	 *
	 * @var string
	 */
	public $payment_action;

	/**
	 * Transaction descriptor to list on consumer's bank account statement
	 *
	 * @var string
	 */
	public $txn_descriptor;

	/**
	 * Transaction descriptor length
	 *
	 * @var int
	 */
	public $txn_descriptor_length = 18;

	/**
	 * Control of WooCommerce's card storage (tokenization) support
	 *
	 * @var bool
	 */
	public $allow_card_saving;

	/**
	 * Gateway HTTP client
	 *
	 * @var Clients\ClientInterface
	 */
	public $client;

	/**
	 * AVS CVN auto reverse condition
	 *
	 * @var bool
	 */
	public $check_avs_cvv;

	/**
	 * AVS result codes
	 *
	 * @var array
	 */
	public $avs_reject_conditions;

	/**
	 * CVN result codes
	 *
	 * @var array
	 */
	public $cvn_reject_conditions;

	protected static string $js_lib_version = 'v1';

	public function __construct( $is_provider = false ) {
		$this->client     = new Clients\SdkClient();

		$this->has_fields = true;
		$this->supports   = array(
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		$this->configure_method_settings();
		$this->init_form_fields();
		$this->init_settings();
		$this->configure_merchant_settings();

		if ( $is_provider ) {
			return;
		}

		$this->add_hooks();
	}

	/**
	 * Sets the necessary WooCommerce payment method settings for exposing the
	 * gateway in the WooCommerce Admin.
	 *
	 * @return
	 */
	abstract public function configure_method_settings();

	/**
	 * Required options for proper client-side configuration.
	 *
	 * @return array
	 */
	abstract public function get_frontend_gateway_options();

	/**
	 * Required options for proper server-side configuration.
	 *
	 * @return array
	 */
	abstract public function get_backend_gateway_options();

	/**
	 * Custom admin options to configure the gateway-specific credentials, features, etc.
	 *
	 * @return array
	 */
	abstract public function get_gateway_form_fields();

	/**
	 * Email address of the first-line support team
	 *
	 * @return string
	 */
	abstract public function get_first_line_support_email();

	/**
	 * Builds payment fields area - including environment indicator
	 */
	public function payment_fields() {
		echo $this->environment_indicator();

		parent::payment_fields();
	}

	/**
	 * Adds environment indicator in sandbox/test mode.
	 */
	public function environment_indicator()
	{
		if (
			! wc_string_to_bool( $this->is_production )
			&& ( $this->id !== 'globalpayments_gpapi' )
		) {
			return sprintf(
				'<div class="woocommerce-globalpayments-sandbox-warning">%s</div>',
				esc_html( 'This page is currently in sandbox/test mode. Do not use real/active card numbers.', 'globalpayments-gateway-provider-for-woocommerce' )
			);
		}
	}

	/**
	 * Get the current gateway provider
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_gateway_provider() {
		if ( ! $this->gateway_provider ) {
			// this shouldn't happen outside of our internal development
			throw new Exception( 'Missing gateway provider configuration' );
		}

		return $this->gateway_provider;
	}

	/**
	 * Sets the configurable merchant settings for use elsewhere in the class
	 *
	 * @return
	 */
	public function configure_merchant_settings() {
		$this->title             = $this->get_option( 'title' );
		$this->enabled           = $this->get_option( 'enabled' );
		$this->payment_action    = $this->get_option( 'payment_action' );
		$this->txn_descriptor    = $this->get_option( 'txn_descriptor' );
		$this->allow_card_saving = $this->get_option( 'allow_card_saving' ) === 'yes';

		foreach ( $this->get_gateway_form_fields() as $key => $options ) {
			if ( ! property_exists( $this, $key ) ) {
				continue;
			}

			$value = $this->get_option( $key );

			if ( 'checkbox' === $options['type'] ) {
				$value = 'yes' === $value;
			}

			$this->{$key} = $value;
		}
	}

	/**
	 * Hook into `woocommerce_credit_card_form_fields` filter
	 *
	 * Replaces WooCommerce's default card inputs for empty container elements
	 * for our secure payment fields (iframes).
	 *
	 * @param $fields
	 * @param $id
	 *
	 * @return array
	 */
	public function woocommerce_credit_card_form_fields( $fields, $id ) {
		if ( $this->id != $id ) {
			return $fields;
		}

		$field_format = $this->secure_payment_field_html_format();
		$fields       = $this->secure_payment_fields();
		$result       = array();

		foreach ( $fields as $key => $field ) {
			$result[ $key ] = sprintf(
				$field_format,
				esc_attr( $this->id ),
				$field['class'],
				$field['label'],
				$field['messages']['validation']
			);
		}

		return $result;
	}

	/**
	 * Enqueues tokenization scripts from Global Payments and WooCommerce
	 *
	 * @return
	 */
	public function tokenization_script() {
		// WooCommerce's scripts for handling stored cards
		parent::tokenization_script();

		if ( ! $this->supports( 'globalpayments_hosted_fields' ) ) {
			return;
		}

		// Global Payments styles for client-side tokenization
		wp_enqueue_style(
			'globalpayments-secure-payment-fields',
			Plugin::get_url( '/assets/frontend/css/globalpayments-secure-payment-fields.css' ),
			array(),
			Plugin::VERSION
		);

		// Global Payments scripts for handling client-side tokenization

		self::hosted_fields_script();

		// include 'wp-i18n' for translation
		$secure_payment_fields_deps = array( 'globalpayments-secure-payment-fields-lib', 'wp-i18n' );

		if ( $this->supports( 'globalpayments_three_d_secure' ) && is_checkout() ) {
			wp_enqueue_script(
				'globalpayments-threedsecure-lib',
				Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
				. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
				array( 'globalpayments-secure-payment-fields-lib' ),
				Plugin::VERSION,
				true
			);
			array_push( $secure_payment_fields_deps, 'globalpayments-threedsecure-lib' );
		}

		$this->helper_script();
		array_push( $secure_payment_fields_deps, 'globalpayments-helper' );

		if ( is_checkout() ) {
			array_push( $secure_payment_fields_deps, 'wc-checkout' );
		}

		wp_enqueue_script(
			'globalpayments-secure-payment-fields',
			Plugin::get_url( '/assets/frontend/js/globalpayments-secure-payment-fields.js' ),
			$secure_payment_fields_deps,
			Plugin::VERSION,
			true
		);

		// set script translation, this will look in plugin languages directory and look for .json translation file
		wp_set_script_translations('globalpayments-secure-payment-fields', 'globalpayments-gateway-provider-for-woocommerce', WP_PLUGIN_DIR . '/'. basename( dirname( __FILE__ , 3 ) ) . '/languages');

		wp_localize_script(
			'globalpayments-secure-payment-fields',
			'globalpayments_secure_payment_fields_params',
			array(
				'id'              => $this->id,
				'gateway_options' => $this->secure_payment_fields_config(),
				'field_options'   => $this->secure_payment_fields(),
				'field_styles'    => $this->secure_payment_fields_styles(),
			)
		);
		if ( $this->supports( 'globalpayments_three_d_secure' ) && is_checkout() ) {
			wp_localize_script(
				'globalpayments-secure-payment-fields',
				'globalpayments_secure_payment_threedsecure_params',
				array(
					'threedsecure' => $this->getThreedsecureFields()
				)
			);
		}
	}

	public function getThreedsecureFields() {
		return array(
				'methodNotificationUrl'     => WC()->api_request_url( 'globalpayments_threedsecure_methodnotification' ),
				'challengeNotificationUrl'  => WC()->api_request_url( 'globalpayments_threedsecure_challengenotification' ),
				'checkEnrollmentUrl'        => WC()->api_request_url( 'globalpayments_threedsecure_checkenrollment' ),
				'initiateAuthenticationUrl' => WC()->api_request_url( 'globalpayments_threedsecure_initiateauthentication' ),
				'ajaxCheckoutUrl'           => \WC_AJAX::get_endpoint( 'checkout' ),
		);
	}

	/**
	 * Enqueues Global Payments JS library (Hosted Fields).
	 */
	public static function hosted_fields_script() {
		if ( wp_script_is( 'globalpayments-secure-payment-fields-lib', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'globalpayments-secure-payment-fields-lib',
			'https://js.globalpay.com/' . static::$js_lib_version . '/globalpayments'
			. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
			array(),
			WC()->version,
			true
		);
	}

	public function helper_script() {
		if ( wp_script_is( 'globalpayments-helper', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script(
			'globalpayments-helper',
			Plugin::get_url( '/assets/frontend/js/globalpayments-helper.js' ),
			array( 'jquery', 'jquery-blockui' ),
			Plugin::VERSION,
			true
		);

		wp_localize_script(
			'globalpayments-helper',
			'globalpayments_helper_params',
			$this->get_helper_params()
		);
	}

	public function get_helper_params() {
		return array(
			'orderInfoUrl' => WC()->api_request_url( 'globalpayments_order_info' ),
			'order'        => $this->get_order_data(),
			'toggle'       => array(
				$this->id,
				GooglePay::PAYMENT_METHOD_ID,
				ApplePay::PAYMENT_METHOD_ID,
			),
			'hide'         => array(
				ClickToPay::PAYMENT_METHOD_ID,
			),
		);
	}

	/**
	 * Configures shared gateway options
	 *
	 * @return
	 */
	public function init_form_fields() {
		$this->form_fields = array_merge(
			array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Gateway', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default' => 'no',
				),
				'title'   => array(
					'title'             => __( 'Title', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'This controls the title which the user sees during checkout.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'           => __( 'Credit Card', 'globalpayments-gateway-provider-for-woocommerce' ),
					'desc_tip'          => true,
					'custom_attributes' => array( 'required' => 'required' ),
				),
			),
			$this->get_gateway_form_fields(),
			array(
				'payment_action'        => array(
					'title'       => __( 'Payment Action', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only for a delayed capture.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => self::TXN_TYPE_SALE,
					'desc_tip'    => true,
					'options'     => array(
						self::TXN_TYPE_SALE      => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
						self::TXN_TYPE_AUTHORIZE => __( 'Authorize only', 'globalpayments-gateway-provider-for-woocommerce' ),
						//self::TXN_TYPE_VERIFY    => __( 'Verify only', 'globalpayments-gateway-provider-for-woocommerce' ),
					),
				),
				'allow_card_saving'     => array(
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
				'txn_descriptor'        => array(
					'title'             => __( 'Order Transaction Descriptor', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'              => 'text',
					'description'       => sprintf(
					/* translators: %s: Email address of support team */
						__( 'During a Capture or Authorize payment action, this value will be passed along as the transaction-specific descriptor listed on the customer\'s bank account. Please contact <a href="mailto:%s?Subject=WooCommerce%%20Transaction%%20Descriptor%%20Option">support</a> with any questions regarding this option (maxLength: 25).', 'globalpayments-gateway-provider-for-woocommerce' ),
						$this->get_first_line_support_email()
					),
					'default'           => '',
					'class'             => 'txn_descriptor',
					'custom_attributes' => array(
						'maxlength' => $this->txn_descriptor_length,
					),
				),
				'check_avs_cvv'         => array(
					'title'       => __( 'Check AVS CVN', 'globalpayments-gateway-provider-for-woocommerce' ),
					'label'       => __( 'Check AVS/CVN result codes and reverse transaction.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => sprintf(
						__( 'This will check AVS/CVN result codes and reverse transaction.', 'globalpayments-gateway-provider-for-woocommerce' )
					),
					'default'     => 'yes'
				),
				'avs_reject_conditions' => array(
					'title'       => __( 'AVS Reject Conditions', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'css'         => 'width: 450px',
					'description' => __( 'Choose for which AVS result codes, the transaction must be auto reversed.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'options'     => $this->avs_rejection_conditions(),
					'default'     => array( "N", "S", "U", "P", "R", "G", "C", "I" ),
				),
				'cvn_reject_conditions' => array(
					'title'       => __( 'CVN Reject Conditions', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'css'         => 'width: 450px',
					'description' => __( 'Choose for which CVN result codes, the transaction must be auto reversed.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'options'     => $this->cvn_rejection_conditions(),
					'default'     => array( "P", "?", "N" ),
				),
			)
		);
	}

	/**
	 * Get credential setting value based on environment.
	 *
	 * @param string $setting
	 *
	 * @return mixed
	 */
	public function get_credential_setting( $setting ) {
		return $this->is_production ? $this->{$setting} : $this->{'sandbox_' . $setting};
	}

	/**
	 * Configuration for the secure payment fields on the client side.
	 *
	 * @return array
	 */
	public function secure_payment_fields_config() {
		try {
			return $this->get_frontend_gateway_options();
		} catch ( \Exception $e ) {
			return array(
				'error'   => true,
				'message' => $e->getMessage(),
				'hide'    => ( GpApiGateway::GATEWAY_ID === $this->id ) ? true : false,
			);
		}
	}

	/**
	 * Configuration for the secure payment fields. Used on server- and
	 * client-side portions of the integration.
	 *
	 * @return array
	 */
	public function secure_payment_fields() {
		return array(
			'card-number-field' => array(
				'class'       => 'card-number',
				'label'       => esc_html__( 'Credit Card Number', 'globalpayments-gateway-provider-for-woocommerce' ),
				'placeholder' => esc_html__( '•••• •••• •••• ••••', 'globalpayments-gateway-provider-for-woocommerce' ),
				'messages'    => array(
					'validation' => esc_html__( 'Please enter a valid Credit Card Number', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
			'card-expiry-field' => array(
				'class'       => 'card-expiration',
				'label'       => esc_html__( 'Credit Card Expiration Date', 'globalpayments-gateway-provider-for-woocommerce' ),
				'placeholder' => esc_html__( 'MM / YYYY', 'globalpayments-gateway-provider-for-woocommerce' ),
				'messages'    => array(
					'validation' => esc_html__( 'Please enter a valid Credit Card Expiration Date', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
			'card-cvc-field'    => array(
				'class'       => 'card-cvv',
				'label'       => esc_html__( 'Credit Card Security Code', 'globalpayments-gateway-provider-for-woocommerce' ),
				'placeholder' => esc_html__( '•••', 'globalpayments-gateway-provider-for-woocommerce' ),
				'messages'    => array(
					'validation' => esc_html__( 'Please enter a valid Credit Card Security Code', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * CSS styles for secure payment fields.
	 *
	 * @return mixed|void
	 */
	public function secure_payment_fields_styles() {
		$image_base = $this->secure_payment_fields_asset_base_url() . '/images';

		$secure_payment_fields_styles = array(
			'html'                          => array(
				'font-size'                => '100%',
				'-webkit-text-size-adjust' => '100%',
			),
			'body'                          => array(),
			'#secure-payment-field-wrapper' => array(
				'position' => 'relative',
			),
			'#secure-payment-field'         => array(
				'background-color' => '#fff',
				'border'           => '1px solid #ccc',
				'border-radius'    => '4px',
				'display'          => 'block',
				'font-size'        => '14px',
				'height'           => '35px',
				'padding'          => '6px 12px',
			),
			'#secure-payment-field:focus'   => array(
				'border'     => '1px solid lightblue',
				'box-shadow' => '0 1px 3px 0 #cecece',
				'outline'    => 'none',
			),

			'button#secure-payment-field.submit'        => array(
				'border'             => '0',
				'border-radius'      => '0',
				'background'         => 'none',
				'background-color'   => '#333333',
				'border-color'       => '#333333',
				'color'              => '#fff',
				'cursor'             => 'pointer',
				'padding'            => '.6180469716em 1.41575em',
				'text-decoration'    => 'none',
				'font-weight'        => '600',
				'text-shadow'        => 'none',
				'display'            => 'inline-block',
				'-webkit-appearance' => 'none',
				'height'             => 'initial',
				'width'              => '100%',
				'flex'               => 'auto',
				'position'           => 'static',
				'margin'             => '0',

				'white-space'   => 'pre-wrap',
				'margin-bottom' => '0',
				'float'         => 'none',

				'font' => '600 1.41575em/1.618 Source Sans Pro,HelveticaNeue-Light,Helvetica Neue Light,
							Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif !important'
			),
			'#secure-payment-field[type=button]:focus'  => array(
				'color'      => '#fff',
				'background' => '#000000',
			),
			'#secure-payment-field[type=button]:hover'  => array(
				'color'      => '#fff',
				'background' => '#000000',
			),
			'.card-cvv'                                 => array(
				'background'      => 'transparent url(' . $image_base . '/cvv.png) no-repeat right',
				'background-size' => '63px 40px'
			),
			'.card-cvv.card-type-amex'                  => array(
				'background'      => 'transparent url(' . $image_base . '/cvv-amex.png) no-repeat right',
				'background-size' => '63px 40px'
			),
			'.card-number::-ms-clear' 		    => array(
				'display' => 'none',
			),
			'input[placeholder]' 			    => array(
				'letter-spacing' => '.5px',
			),
			'img.card-number-icon' 			    => array(
				'background' => 'transparent url(' . $image_base . '/logo-unknown@2x.png) no-repeat',
				'background-size' => '100%',
				'width' => '65px',
				'height' => '40px',
				'position' => 'absolute',
				'right' => '0',
				'top' => '25px',
				'margin-top' => '-20px',
				'background-position' => '50% 50%',
			),
			'img.card-number-icon[src$=\'/gp-cc-generic.svg\']' => array(
				'background' => 'transparent url(' . $image_base . '/logo-mastercard@2x.png) no-repeat',
				'background-size' => '100%',
				'background-position-y' => 'bottom',
			),
			'img.card-number-icon.card-type-diners'	    	    => array(
				'background' => 'transparent url(' . $image_base . '/gp-cc-diners.svg) no-repeat',
				'background-size' => '80%',
				'background-position-x' => '10px',
				'background-position-y' => '3px',
			),
			'img.card-number-icon.invalid.card-type-amex'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-amex@2x.png) no-repeat 140%',
				'background-size' => '85%',
				'background-position-y' => '87%',
			),
			'img.card-number-icon.invalid.card-type-discover'   => array(
				'background' => 'transparent url(' . $image_base . '/logo-discover@2x.png) no-repeat',
				'background-size' => '110%',
				'background-position-y' => '94%',
				'width' => '85px',
			),
			'img.card-number-icon.invalid.card-type-jcb'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-jcb@2x.png) no-repeat 175%',
				'background-size' => '95%',
				'background-position-y' => '85%',
			),
			'img.card-number-icon.invalid.card-type-mastercard' => array(
				'background' => 'transparent url(' . $image_base . '/logo-mastercard@2x.png) no-repeat',
				'background-size' => '113%',
				'background-position-y' => 'bottom',
			),
			'img.card-number-icon.invalid.card-type-visa'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-visa@2x.png) no-repeat',
				'background-size' => '120%',
				'background-position-y' => 'bottom',
			),
			'img.card-number-icon.valid.card-type-amex'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-amex@2x.png) no-repeat 140%',
				'background-size' => '85%',
				'background-position-y' => '-9px',
			),
			'img.card-number-icon.valid.card-type-discover'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-discover@2x.png) no-repeat',
				'background-size' => '110%',
				'background-position-y' => '-5px',
				'width' => '85px',
			),
			'img.card-number-icon.valid.card-type-jcb'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-jcb@2x.png) no-repeat 175%',
				'background-size' => '95%',
				'background-position-y' => '-7px',
			),
			'img.card-number-icon.valid.card-type-mastercard'   => array(
				'background' => 'transparent url(' . $image_base . '/logo-mastercard@2x.png) no-repeat',
				'background-size' => '113%',
				'background-position-y' => '2px',
			),
			'img.card-number-icon.valid.card-type-visa'	    => array(
				'background' => 'transparent url(' . $image_base . '/logo-visa@2x.png) no-repeat',
				'background-size' => '120%',
				'background-position-y' => '0px',
			),
			'#field-validation-wrapper' => array(
				'background' => '#e2401c',
				'font-family' => '"Source Sans Pro","HelveticaNeue-Light","Helvetica Neue Light","Helvetica Neue",Helvetica,Arial,"Lucida Grande",sans-serif !important',
				'font-size' => '13px !important',
				'padding' => '6px 12px',
				'border-radius' => '4px',
				'border-left' => '.6180469716em solid rgba(0,0,0,.15)',
				'color' => '#fff !important',
			),
		);

		/**
		 * Allow hosted fields styling customization.
		 *
		 * @param array $secure_payment_fields_styles CSS styles.
		 */
		return apply_filters( 'globalpayments_secure_payment_fields_styles', wp_json_encode( $secure_payment_fields_styles ) );
	}

	/**
	 * Base assets URL for secure payment fields.
	 *
	 * @return string
	 */
	protected function secure_payment_fields_asset_base_url() {
		if ( $this->is_production ) {
			return 'https://js.globalpay.com/' . static::$js_lib_version;
		}

		return 'https://js-cert.globalpay.com/' . static::$js_lib_version;
	}

	public function save_payment_method_checkbox() {
		if ( ! $this->allow_card_saving ) {
			return;
		}

		parent::save_payment_method_checkbox();
	}

	/**
	 * The HTML template string for a secure payment field
	 *
	 * Format directives:
	 *
	 * 1) Gateway ID
	 * 2) Field CSS class
	 * 3) Field label
	 * 4) Field validation message
	 *
	 * @return string
	 */
	protected function secure_payment_field_html_format() {
		return (
		'<div class="form-row form-row-wide globalpayments %1$s %2$s">
				<label for="%1$s-%2$s">%3$s&nbsp;<span class="required">*</span></label>
				<div id="%1$s-%2$s"></div>
				<ul class="woocommerce-globalpayments-validation-error" style="display: none;">
					<li>%4$s</li>
				</ul>
			</div>'
		);
	}

	/**
	 * Adds necessary gateway-specific hooks
	 *
	 * @return
	 */
	protected function add_hooks() {
		// hooks always active for the gateway
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array(
				$this,
				'admin_enforce_single_gateway'
			) );
			add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'woocommerce_new_order', array( $this, 'admin_add_order_note_after_order_created' ) );
		}
		add_action( 'woocommerce_api_globalpayments_order_info', array(
			$this,
			'get_order_info'
		) );
		add_action( 'admin_enqueue_scripts', array(
			$this,
			'check_api_credentials'
		) );
		add_action( 'woocommerce_api_globalpayments_check_api_credentials_handler', array(
			$this,
			'check_api_credentials_handler'
		) );

		if ( 'no' === $this->enabled ) {
			return;
		}
		// hooks only active when the gateway is enabled
		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'woocommerce_credit_card_form_fields' ), 10, 2 );

		if ( str_contains( $_SERVER['REQUEST_URI'], 'add-payment-method' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'tokenization_script' ) );
			add_filter( 'woocommerce_available_payment_gateways', array(
				$this,
				'woocommerce_available_payment_gateways'
			) );
		}
	}

	/**
	 * Add order note when creating an order from admin with transaction ID
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function admin_add_order_note_after_order_created( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			return;
		}
		if ( $this->id != $order->get_payment_method() ) {
			return;
		}
		if ( empty( $order->get_transaction_id() ) ) {
			return;
		}

		$order->add_order_note(
			sprintf(
                /* translators: %s: Order created with Transaction ID */
				esc_html__( 'Order created with Transaction ID: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$order->get_transaction_id()
			)
		);
	}

	/**
	 * Handle payment functions
	 *
	 * @param int $order_id
	 *
	 * @return array
	 * @throws ApiException
	 */
	public function process_payment( $order_id ) {
		$order         = wc_get_order( $order_id );
		$request       = $this->prepare_request( $this->payment_action, $order );

		$request->set_request_data( array(
			'dynamic_descriptor' => $this->txn_descriptor,
		) );

		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			if ( $this->payment_action == self::TXN_TYPE_AUTHORIZE ) {
				$this->payment_action = __( 'authorized', 'globalpayments-gateway-provider-for-woocommerce' );
			} else {
				$this->payment_action = __( 'charged', 'globalpayments-gateway-provider-for-woocommerce' );

				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order->add_meta_data( '_globalpayments_payment_captured', 'is_captured', true );
				} else {
					add_post_meta( $order->get_id(), '_globalpayments_payment_captured', 'is_captured', true );
				}
			}

			$note_text = sprintf(
				'%1$s%2$s %3$s. Transaction ID: %4$s.',
				get_woocommerce_currency_symbol( $order->get_currency() ),
				$order->get_total(),
				$this->payment_action,
				$order->get_transaction_id()
			);
			// If the order contains a subscription, add the muti-use token to the order meta for repeat payments.
			if ( function_exists( "wcs_order_contains_subscription" ) && wcs_order_contains_subscription( $order ) && $is_successful ) {
				$order->add_meta_data( "_GP_multi_use_token", $response->token, true );
				$order->save_meta_data();
			}

			$order->add_order_note( $note_text );
		}

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}

	/**
	 * Handle adding new cards via 'My Account'
	 *
	 * @return array
	 * @throws Exception
	 */
	public function add_payment_method() {
		$request  = $this->prepare_request( self::TXN_TYPE_VERIFY );
		$redirect = wc_get_endpoint_url( 'payment-methods' );

		try {
			$response      = $this->submit_request( $request );
			$is_successful = $this->handle_response( $request, $response );
		} catch ( Exception $e ) {
			return array(
				'result'   => 'failure',
				'redirect' => $redirect,
			);
		}

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $redirect,
		);
	}

	/**
	 * Handle online refund requests via WP Admin > WooCommerce > Edit Order
	 *
	 * @param int    $order_id
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws ApiException
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order                  = wc_get_order( $order_id );
		$details                = $this->get_transaction_details_by_txn_id( $order->get_transaction_id() );
		$is_order_txn_id_active = $this->is_transaction_active( $details );
		$txn_type               = $is_order_txn_id_active ? self::TXN_TYPE_REVERSAL : self::TXN_TYPE_REFUND;

		$request      = $this->prepare_request( $txn_type, $order );

		if ( null != $amount ) {
			$amount = str_replace( ',', '.', $amount );
			$amount = number_format( (float) round( $amount, 2, PHP_ROUND_HALF_UP ), 2, '.', '' );
			if ( ! is_numeric( $amount ) ) {
				throw new Exception( esc_html__( 'Refund amount must be a valid number', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		}
		$request->set_request_data( array(
			'refund_amount' => $amount,
			'refund_reason' => $reason,
		) );
		$request_args = $request->get_args();
		if ( 0 >= (float)$request_args[ RequestArg::AMOUNT ] ) {
			throw new Exception( esc_html__( 'Refund amount must be greater than zero.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			$note_text = sprintf(
                /* translators: %1$s%2$s was reversed or refunded. Transaction ID: %3$s */
				esc_html__( '%1$s%2$s was reversed or refunded. Transaction ID: %3$s ', 'globalpayments-gateway-provider-for-woocommerce' ),
				get_woocommerce_currency_symbol(), $amount, $response->transactionReference->transactionId
			);

			$order->add_order_note( $note_text );
		}

		return $is_successful;
	}

	/**
	 * @param Transaction $response
	 * @return bool
	 */
	public static function is_successful_capture_response( Transaction $response ): bool {
		return "00" === $response->responseCode && "Success" === $response->responseMessage
			|| 'SUCCESS' === $response->responseCode && "CAPTURED" === $response->responseMessage;
	}

	/**
	 * Handle capture auth requests via WP Admin > WooCommerce > Edit Order
	 *
	 * @param $order
	 *
	 * @return Transaction
	 * @throws Exception
	 */
	public static function capture_credit_card_authorization( $order ) {
		switch ( $order->get_payment_method() ) {
			case HeartlandGateway::GATEWAY_ID:
				$gateway = new HeartlandGateway();
				break;
			case TransitGateway::GATEWAY_ID:
				$gateway = new TransitGateway();
				break;
			case GeniusGateway::GATEWAY_ID:
				$gateway = new GeniusGateway();
				break;
			case GooglePay::PAYMENT_METHOD_ID:
			case ApplePay::PAYMENT_METHOD_ID:
				$gateway = Plugin::get_active_gateway() == HeartlandGateway::GATEWAY_ID ? new HeartlandGateway() : new GpApiGateway();
				break;
			case GpApiGateway::GATEWAY_ID:
			case Affirm::PAYMENT_METHOD_ID:
			case Klarna::PAYMENT_METHOD_ID:
			case Clearpay::PAYMENT_METHOD_ID:
			case OpenBanking::PAYMENT_METHOD_ID:
			case Paypal::PAYMENT_METHOD_ID:
				$gateway = new GpApiGateway();
				break;
			case GpiTransactionApiGateway::GATEWAY_ID:
				$gateway = new GpiTransactionApiGateway();
				break;
		};

		$request = $gateway->prepare_request( self::TXN_TYPE_CAPTURE, $order );

		try {
			$response = $gateway->submit_request( $request );

			if ( self::is_successful_capture_response( $response ) ) {

				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order->add_meta_data( '_globalpayments_payment_captured', 'is_captured', true );
					$order->delete_meta_data( '_globalpayments_payment_action' );
				} else {
					add_post_meta( $order->get_id(), '_globalpayments_payment_captured', 'is_captured', true );
					delete_post_meta( $order->get_id(), '_globalpayments_payment_action' );
				}

				$order->add_order_note(
					sprintf(
                        /* translators: %s: Transaction ID for the capture */
						esc_html__( 'Transaction captured. Transaction ID for the capture: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						$response->transactionReference->transactionId
					)
				);
				$order->save();
			}

			return $response;
		} catch ( Exception $e ) {
			wp_die(
				esc_html($e->getMessage()),
				'',
				array(
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Get transaction details from Global Payments transaction ID.
	 *
	 * @param string $txn_id
	 *
	 * @return Transaction
	 * @throws Exception
	 */
	public function get_transaction_details_by_txn_id( $txn_id ) {
		$request = $this->prepare_request( self::TXN_TYPE_REPORT_TXN_DETAILS );
		$request->set_request_data( array(
				'txn_id' => $txn_id,
			)
		);

		return $this->submit_request( $request );
	}

	/**
	 * Creates the necessary request based on the transaction type
	 *
	 * @param               $txn_type
	 * @param WC_Order|null $order
	 * @param array|null    $configData
	 *
	 * @return Requests\RequestInterface
	 * @throws Exception
	 */
	public function prepare_request( $txn_type, WC_Order $order = null, $configData = null ) {
		$map = array(
			self::TXN_TYPE_AUTHORIZE               => Requests\AuthorizationRequest::class,
			self::TXN_TYPE_SALE                    => Requests\SaleRequest::class,
			self::TXN_TYPE_VERIFY                  => Requests\VerifyRequest::class,
			self::TXN_TYPE_CREATE_TRANSACTION_KEY  => Requests\CreateTransactionKeyRequest::class,
			self::TXN_TYPE_CREATE_MANIFEST         => Requests\CreateManifestRequest::class,
			self::TXN_TYPE_REFUND                  => Requests\RefundRequest::class,
			self::TXN_TYPE_REVERSAL                => Requests\ReversalRequest::class,
			self::TXN_TYPE_REPORT_TXN_DETAILS      => Requests\TransactionDetailRequest::class,
			self::TXN_TYPE_CAPTURE                 => Requests\CaptureAuthorizationRequest::class,
			self::TXN_TYPE_GET_ACCESS_TOKEN        => Requests\GetAccessTokenRequest::class,
			self::TXN_TYPE_CHECK_ENROLLMENT        => Requests\ThreeDSecure\CheckEnrollmentRequest::class,
			self::TXN_TYPE_INITIATE_AUTHENTICATION => Requests\ThreeDSecure\InitiateAuthenticationRequest::class,
			self::TXN_TYPE_DW_AUTHORIZATION        => Requests\DigitalWallets\AuthorizationRequest::class,
			self::TXN_TYPE_BNPL_AUTHORIZE          => Requests\BuyNowPayLater\InitiatePaymentRequest::class,
			self::TXN_TYPE_OB_AUTHORIZATION        => Requests\OpenBanking\InitiatePaymentRequest::class,
			self::TXN_TYPE_PAYPAL_INITIATE         => Requests\Apm\InitiatePaymentRequest::class,
			self::TXN_TYPE_SUBSCRIPTION_PAYMENT    => Requests\Subscriptions\SubscriptionRequest::class
		);

		if ( ! isset( $map[ $txn_type ] ) ) {
			throw new \Exception( 'Cannot perform transaction' );
		}

		$backendGatewayOptions = $this->get_backend_gateway_options();
		if ( ! empty( $configData ) ) {
			$backendGatewayOptions = array_merge( $backendGatewayOptions, $configData );
		}

		$request = $map[ $txn_type ];

		return new $request(
			$this->id,
			$order,
			array_merge( array( 'gatewayProvider' => $this->get_gateway_provider() ), $backendGatewayOptions )
		);
	}

	/**
	 * Executes the prepared request
	 *
	 * @param Requests\RequestInterface $request
	 *
	 * @return Transaction
	 */
	public function submit_request( Requests\RequestInterface $request ) {
		return $this->client->set_request( $request )->execute();
	}

	/**
	 * @param Transaction $response
	 * @return bool
	 */
	public static function is_partially_approved( Transaction $response ): bool {
		return $response->responseCode === '10' || $response->responseMessage === 'Partially Approved'
			|| str_starts_with( $response->responseCode, 'partially_approved' );
	}

	/**
	 * @param Transaction $response
	 * @return bool
	 */
	public static function is_transaction_declined( Transaction $response ): bool {
		return $response->responseCode !== '00' && 'SUCCESS' !== $response->responseCode
			&& ! str_starts_with( $response->responseCode, 'approved' );
	}

	protected function map_response_code_to_friendly_message( $responseCode ) {
		return $responseCode;
	}

	/**
	 * Reacts to the transaction response
	 *
	 * @param Requests\RequestInterface $request
	 * @param Transaction               $response
	 *
	 * @return bool
	 * @throws ApiException
	 */
	public function handle_response( Requests\RequestInterface $request, Transaction $response ) {
		if ( self::is_transaction_declined( $response ) || $response->responseMessage === 'Partially Approved' ) {
			if ( self::is_partially_approved( $response ) ) {
				try {
					$response->void()->withDescription( 'POST_AUTH_USER_DECLINE' )->execute();

					return false;
				} catch ( \Exception $e ) {
					/** om nom */
				}
			}

			throw new ApiException( esc_html(Utils::map_response_code_to_friendly_message( $response->responseCode )) );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		if ( self::is_transaction_declined( $response ) ) {
			$woocommerce     = WC();
			$decline_message = $this->get_decline_message( $response->responseCode );

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $decline_message, 'error' );
			} elseif ( isset( $woocommerce ) && property_exists( $woocommerce, 'add_error' ) ) {
				$woocommerce->add_error( $decline_message );
			}

			return false;
		}

		//reverse incase of AVS/CVN failure
		if ( ! empty( $response->transactionReference->transactionId ) && $this->get_option( 'check_avs_cvv' ) === 'yes' ) {
			if ( ! empty( $response->avsResponseCode ) || ! empty( $response->cvnResponseCode ) ) {
				//check admin selected decline condtions
				if ( in_array( $response->avsResponseCode, $this->get_option( 'avs_reject_conditions' ) ) ||
					 in_array( $response->cvnResponseCode, $this->get_option( 'cvn_reject_conditions' ) ) ) {
					$data = $request->order->get_data();

					if ( ! is_array( $data ) ) {
						$data = [];
					}
					if ( $this->id !== TransitGateway::GATEWAY_ID ) {
						Transaction::fromId( $response->transactionReference->transactionId )
							->reverse( $data['total'] )
							->execute();
					} else {
						Transaction::fromId( $response->transactionReference->transactionId )
							->refund( $data['total'] )
							->withCurrency( $data['currency'] )
							->execute();
					}

					throw new \Exception( esc_html(Utils::map_response_code_to_friendly_message()) );
				}
			}
		}

		$handlers = array(
			Handlers\PaymentActionHandler::class,
			Handlers\DelayedAuthorizationHandler::class,
			Handlers\PaymentTokenHandler::class,
		);

		foreach ( $handlers as $handler ) {
			/**
			 * Current handler
			 *
			 * @var Handlers\HandlerInterface $h
			 */
			$h = new $handler( $request, $response );
			$h->handle();
		}

		return true;
	}

	// should be overridden by gateway implementations
	protected function is_transaction_active( TransactionSummary $details ) {
		return false;
	}

	/**
	 * Should be overridden by each gateway implementation
	 *
	 * @return string
	 */
	public function get_decline_message( string $response_code ) {
		return 'An error occurred while processing the card.';
	}

	/**
	 * Adds delayed capture functionality to the "Edit Order" screen
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public static function add_capture_order_action( $actions ) {
		global $theorder;

		$payment_action = $theorder->get_meta( '_globalpayments_payment_action' );
		if ( AbstractGateway::TXN_TYPE_AUTHORIZE !== $payment_action &&
				AbstractGateway::TXN_TYPE_DW_AUTHORIZATION !== $payment_action ) {
			return $actions;
		}

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order_is_captured = $theorder->get_meta( '_globalpayments_payment_captured' );
		} else {
			$order_is_captured = get_post_meta( $theorder->get_id(), '_globalpayments_payment_captured', true );
		}

		if ( $order_is_captured === 'is_captured' || $payment_action === AbstractGateway::TXN_TYPE_SALE ) {
			return $actions;
		}

		$actions['capture_credit_card_authorization'] = __( 'Capture credit card authorization', 'globalpayments-gateway-provider-for-woocommerce' );

		return $actions;
	}

	public function get_order_info() {
		if ( ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		wp_send_json( [
				'error'   => false,
				'message' => $this->get_order_data(),
			]
		);
	}

	public function get_session_amount() {
		if ( is_admin() ) {
			return null;
		}
		$cart_totals = WC()->session->get( 'cart_totals' );

		return round( $cart_totals['total'], 2 );
	}

	protected function get_order_data() {
		return array(
			'id'       => absint( get_query_var( 'order-pay' ) ),
			'amount'   => wc_format_decimal( $this->get_order_total(), 2 ),
			'currency' => get_woocommerce_currency(),
		);
	}

	/**
	 * Disable adding new cards via 'My Account', if `Allow Card Saving` option not checked in admin.
	 *
	 * @param array $available_gateways
	 *
	 * @return array
	 */
	public function woocommerce_available_payment_gateways( $available_gateways ) {
		if ( ! $this->allow_card_saving ) {
			unset( $available_gateways[ $this->id ] );
		}

		return $available_gateways;
	}

	public function avs_rejection_conditions() {
		return array(
			'A' => __( 'A - Address matches, zip No Match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'N' => __( 'N - Neither address or zip code match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'R' => __( 'R - Retry - system unable to respond', 'globalpayments-gateway-provider-for-woocommerce' ),
			'U' => __( 'U - Visa / Discover card AVS not supported', 'globalpayments-gateway-provider-for-woocommerce' ),
			'S' => __( 'S - Master / Amex card AVS not supported', 'globalpayments-gateway-provider-for-woocommerce' ),
			'Z' => __( 'Z - Visa / Discover card 9-digit zip code match, address no match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'W' => __( 'W - Master / Amex card 9-digit zip code match, address no match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'Y' => __( 'Y - Visa / Discover card 5-digit zip code and address match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'X' => __( 'X - Master / Amex card 5-digit zip code and address match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'G' => __( 'G - Address not verified for International transaction', 'globalpayments-gateway-provider-for-woocommerce' ),
			'B' => __( 'B - Address match, Zip not verified', 'globalpayments-gateway-provider-for-woocommerce' ),
			'C' => __( 'C - Address and zip mismatch', 'globalpayments-gateway-provider-for-woocommerce' ),
			'D' => __( 'D - Address and zip match', 'globalpayments-gateway-provider-for-woocommerce' ),
			'I' => __( 'I - AVS not verified for International transaction', 'globalpayments-gateway-provider-for-woocommerce' ),
			'M' => __( 'M - Street address and postal code matches', 'globalpayments-gateway-provider-for-woocommerce' ),
			'P' => __( 'P - Address and Zip not verified', 'globalpayments-gateway-provider-for-woocommerce' ),
		);
	}

	public function cvn_rejection_conditions() {
		return array(
			'N' => __( 'N - Not Matching', 'globalpayments-gateway-provider-for-woocommerce' ),
			'P' => __( 'P - Not Processed', 'globalpayments-gateway-provider-for-woocommerce' ),
			'S' => __( 'S - Result not present', 'globalpayments-gateway-provider-for-woocommerce' ),
			'U' => __( 'U - Issuer not certified', 'globalpayments-gateway-provider-for-woocommerce' ),
			'?' => __( '? - CVV unrecognized', 'globalpayments-gateway-provider-for-woocommerce' )
		);
	}

	/**
	 * Enforce single GlobalPayments gateway activation.
	 *
	 * @param array $settings Admin settings
	 *
	 * @return mixed
	 */
	public function admin_enforce_single_gateway( $settings ) {
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return $settings;
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $available_gateways[ $this->id ] ) ) {
			return $settings;
		}
		foreach ( $available_gateways as $gateway ) {
			if ( $gateway instanceof AbstractGateway ) {
				$settings['enabled'] = 'no';
				add_action( 'woocommerce_sections_checkout', function () use ( $gateway ) {
						echo '<div id="message" class="error inline"><p><strong>' .
						sprintf(
                            /* translators: %s: You can enable only one GlobalPayments gateway at a time */
							esc_html__( 'You can enable only one GlobalPayments gateway at a time. Please disable %s first!', 'globalpayments-gateway-provider-for-woocommerce' ),
							esc_html($gateway->method_title)
						) . '</strong></p></div>';
					}
				);

				return $settings;
			}
		}

		return $settings;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix || isset( $_GET['tab'] ) && 'checkout' !== $_GET['tab'] ) {
			return;
		}
		if ( empty( $_GET['section'] ) ) {
			if ( wp_script_is( 'globalpayments-enforce-single-gateway', 'enqueued' ) ) {
				return;
			}

			wp_enqueue_script(
				'globalpayments-enforce-single-gateway',
				Plugin::get_url( '/assets/admin/js/globalpayments-enforce-single-gateway.js' ),
				array(
					'wp-i18n' // include 'wp-i18n' for translation
				)
			);

			// set script translation, this will look in plugin languages directory and look for .json translation file
			wp_set_script_translations('globalpayments-enforce-single-gateway', 'globalpayments-gateway-provider-for-woocommerce', WP_PLUGIN_DIR . '/'. basename( dirname( __FILE__ , 3 ) ) . '/languages');


			wp_localize_script(
				'globalpayments-enforce-single-gateway',
				'globalpayments_enforce_single_gateway_params',
				$this->get_single_toggle_gateways()
			);

			return;
		}
		$section = sanitize_text_field( wp_unslash( $_GET['section'] ) );

		if ( $this->id != $section ) {
			return;
		}

		wp_enqueue_script(
			'globalpayments-admin',
			Plugin::get_url( '/assets/admin/js/globalpayments-admin.js' ),
			array(
				'wp-i18n' // include 'wp-i18n' for translation
			),
			WC()->version,
			true
		);

		// set script translation, this will look in plugin languages directory and look for .json translation file
		wp_set_script_translations('globalpayments-admin', 'globalpayments-gateway-provider-for-woocommerce', WP_PLUGIN_DIR . '/'. basename( dirname( __FILE__ , 3 ) ) . '/languages');

		wp_localize_script(
			'globalpayments-admin',
			'globalpayments_admin_params',
			array(
				'gateway_id' => $section,
			)
		);
	}

	/**
	 * Get all gateway ids for enforcing single toggle
	 *
	 * @return array
	 */
	private function get_single_toggle_gateways() {
		return array(
			GpApiGateway::GATEWAY_ID,
			HeartlandGateway::GATEWAY_ID,
			TransitGateway::GATEWAY_ID,
			GeniusGateway::GATEWAY_ID,
			GpiTransactionApiGateway::GATEWAY_ID
		);
	}
}
