<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

defined( 'ABSPATH' ) || exit;

use Exception;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use WC_Payment_Gateway_CC;
use WC_Order;
use GlobalPayments\Api\Entities\Transaction;

/**
 * Shared gateway method implementations
 */
abstract class AbstractGateway extends WC_Payment_Gateway_Cc {
	/**
	 * Defines production environment
	 */
	const ENVIRONMENT_PRODUCTION = 'production';

	/**
	 * Defines sandbox environment
	 */
	const ENVIRONMENT_SANDBOX = 'sandbox';

	// auth requests
	const TXN_TYPE_AUTHORIZE = 'authorize';
	const TXN_TYPE_SALE      = 'charge';
	const TXN_TYPE_VERIFY    = 'verify';

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
	 * Indicates if the gateway is digital wallet
	 *
	 * @var boolean
	 */
	public $is_digital_wallet = false;

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
	protected $client;

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
		$this->environment_indicator();
		parent::payment_fields();
	}

	/**
	 * Adds environment indicator in sandbox/test mode.
	 */
	protected function environment_indicator() {
		if ( ! wc_string_to_bool( $this->is_production ) ) {
			echo sprintf( '<div class="woocommerce-globalpayments-sandbox-warning">%s</div>',
				__( 'This page is currently in sandbox/test mode. Do not use real/active card numbers.', 'globalpayments-gateway-provider-for-woocommerce' )
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
	 * @return array
	 */
	public function woocommerce_credit_card_form_fields() {
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
			WC()->version
		);

		// Global Payments scripts for handling client-side tokenization
		wp_enqueue_script(
			'globalpayments-secure-payment-fields-lib',
			'https://js.globalpay.com/v1/globalpayments'
			. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
			array(),
			WC()->version,
			true
		);

		$secure_payment_fields_deps = array( 'globalpayments-secure-payment-fields-lib' );
		if ( $this->supports( 'globalpayments_three_d_secure' ) && is_checkout() ) {
			wp_enqueue_script(
				'globalpayments-threedsecure-lib',
				Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
				. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
				array( 'globalpayments-secure-payment-fields-lib' ),
				WC()->version,
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
			WC()->version,
			true
		);

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
					'threedsecure' => array(
						'methodNotificationUrl'     => WC()->api_request_url( 'globalpayments_threedsecure_methodnotification' ),
						'challengeNotificationUrl'  => WC()->api_request_url( 'globalpayments_threedsecure_challengenotification' ),
						'checkEnrollmentUrl'        => WC()->api_request_url( 'globalpayments_threedsecure_checkenrollment' ),
						'initiateAuthenticationUrl' => WC()->api_request_url( 'globalpayments_threedsecure_initiateauthentication' ),
						'ajaxCheckoutUrl'           => \WC_AJAX::get_endpoint( 'checkout' ),
					)
				)
			);
		}
	}

	public function helper_script() {
		wp_enqueue_script(
			'globalpayments-helper',
			Plugin::get_url( '/assets/frontend/js/globalpayments-helper.js' ),
			array( 'jquery', 'jquery-blockui' ),
			WC()->version,
			true
		);

		wp_localize_script(
			'globalpayments-helper',
			'globalpayments_helper_params',
			array(
				'orderInfoUrl' => WC()->api_request_url( 'globalpayments_order_info' ),
				'order'        => $this->get_order_data(),
			)
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
						__( 'During a Capture or Authorize payment action, this value will be passed along as the transaction-specific descriptor listed on the customer\'s bank account. Please contact <a href="mailto:%s?Subject=WooCommerce%%20Transaction%%20Descriptor%%20Option">support</a> with any questions regarding this option.', 'globalpayments-gateway-provider-for-woocommerce' ),
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
	protected function get_credential_setting( $setting ) {
		return $this->is_production ? $this->{$setting} : $this->{'sandbox_' . $setting};
	}

	/**
	 * Configuration for the secure payment fields on the client side.
	 *
	 * @return array
	 */
	protected function secure_payment_fields_config() {
		try {
			return $this->get_frontend_gateway_options();
		} catch ( \Exception $e ) {
			return array(
				'error'   => true,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Configuration for the secure payment fields. Used on server- and
	 * client-side portions of the integration.
	 *
	 * @return array
	 */
	protected function secure_payment_fields() {
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
	protected function secure_payment_fields_styles() {
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
			'.card-number'                              => array(
				'background'      => 'transparent url(' . $image_base . '/logo-unknown@2x.png) no-repeat right',
				'background-size' => '55px 35px'
			),
			'.card-number.invalid.card-type-amex'       => array(
				'background'            => 'transparent url(' . $image_base . '/logo-amex@2x.png) no-repeat right',
				'background-position-y' => '-41px',
				'background-size'       => '50px 90px'
			),
			'.card-number.invalid.card-type-discover'   => array(
				'background'            => 'transparent url(' . $image_base . '/logo-discover@2x.png) no-repeat right',
				'background-position-y' => '-44px',
				'background-size'       => '85px 90px'
			),
			'.card-number.invalid.card-type-jcb'        => array(
				'background'            => 'transparent url(' . $image_base . '/logo-jcb@2x.png) no-repeat right',
				'background-position-y' => '-44px',
				'background-size'       => '55px 94px'
			),
			'.card-number.invalid.card-type-mastercard' => array(
				'background'            => 'transparent url(' . $image_base . '/logo-mastercard@2x.png) no-repeat right',
				'background-position-y' => '-41px',
				'background-size'       => '82px 86px'
			),
			'.card-number.invalid.card-type-visa'       => array(
				'background'            => 'transparent url(' . $image_base . '/logo-visa@2x.png) no-repeat right',
				'background-position-y' => '-44px',
				'background-size'       => '83px 88px',
			),
			'.card-number.valid.card-type-amex'         => array(
				'background'            => 'transparent url(' . $image_base . '/logo-amex@2x.png) no-repeat right',
				'background-position-y' => '3px',
				'background-size'       => '50px 90px',
			),
			'.card-number.valid.card-type-discover'     => array(
				'background'            => 'transparent url(' . $image_base . '/logo-discover@2x.png) no-repeat right',
				'background-position-y' => '1px',
				'background-size'       => '85px 90px'
			),
			'.card-number.valid.card-type-jcb'          => array(
				'background'            => 'transparent url(' . $image_base . '/logo-jcb@2x.png) no-repeat right top',
				'background-position-y' => '2px',
				'background-size'       => '55px 94px'
			),
			'.card-number.valid.card-type-mastercard'   => array(
				'background'            => 'transparent url(' . $image_base . '/logo-mastercard@2x.png) no-repeat right',
				'background-position-y' => '2px',
				'background-size'       => '82px 86px'
			),
			'.card-number.valid.card-type-visa'         => array(
				'background'      => 'transparent url(' . $image_base . '/logo-visa@2x.png) no-repeat right top',
				'background-size' => '82px 86px'
			),
			'.card-number::-ms-clear'                   => array(
				'display' => 'none',
			),
			'input[placeholder]'                        => array(
				'letter-spacing' => '.5px',
			),
		);

		/**
		 * Allow hosted fields styling customization.
		 *
		 * @param array $secure_payment_fields_styles CSS styles.
		 */
		return apply_filters( 'globalpayments_secure_payment_fields_styles', json_encode( $secure_payment_fields_styles ) );
	}

	/**
	 * Base assets URL for secure payment fields.
	 *
	 * @return string
	 */
	protected function secure_payment_fields_asset_base_url() {
		if ( $this->is_production ) {
			return 'https://js.globalpay.com/v1';
		}

		return 'https://js-cert.globalpay.com/v1';
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

		if ( 'no' === $this->enabled ) {
			return;
		}
		// hooks only active when the gateway is enabled
		if ( ! $this->is_digital_wallet ) {
			add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'woocommerce_credit_card_form_fields' ) );
		}

		add_action( 'woocommerce_api_globalpayments_order_info', array(
			$this,
			'get_order_info'
		) );

		if ( is_add_payment_method_page() ) {
			if ( ! $this->is_digital_wallet ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'tokenization_script' ) );
			}
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
		if ( empty( $order) ) {
			return;
		}
		if ( $this->id != $order->get_payment_method() ) {
			return;
		}
		if ( empty( $order->get_transaction_id() )) {
			return;
		}

		$order->add_order_note( __( 'Order created with Transaction ID: ' ) . $order->get_transaction_id() );

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
		));

		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			$note_text = sprintf(
				'%1$s%2$s %3$s. Transaction ID: %4$s.',
				get_woocommerce_currency_symbol( $order->get_currency() ),
				$order->get_total(),
				$this->payment_action == self::TXN_TYPE_AUTHORIZE ?
					__( 'authorized', 'globalpayments-gateway-provider-for-woocommerce' ) :
					__( 'charged', 'globalpayments-gateway-provider-for-woocommerce' ),
				$order->get_transaction_id()
			);

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
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws ApiException
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$details                = $this->get_transaction_details( $order_id );
		$is_order_txn_id_active = $this->is_transaction_active( $details );
		$txn_type               = $is_order_txn_id_active ? self::TXN_TYPE_REVERSAL : self::TXN_TYPE_REFUND;

		$order        = wc_get_order( $order_id );
		$request      = $this->prepare_request( $txn_type, $order );

		if ( null != $amount ) {
			$amount = str_replace( ',', '.', $amount );
			$amount = number_format( (float)round( $amount, 2, PHP_ROUND_HALF_UP ), 2, '.', '' );
			if ( ! is_numeric( $amount ) ) {
				throw new Exception( __( 'Refund amount must be a valid number', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		}
		$request->set_request_data( array(
			'refund_amount' => $amount,
			'refund_reason' => $reason,
		));
		$request_args = $request->get_args();
		if ( 0 >= (float)$request_args[ RequestArg::AMOUNT ] ) {
			throw new Exception( __( 'Refund amount must be greater than zero.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			$note_text = sprintf(
				'%s%s was reversed or refunded. Transaction ID: %s ',
				get_woocommerce_currency_symbol(), $amount, $response->transactionReference->transactionId
			);

			$order->add_order_note( $note_text );
		}

		return $is_successful;
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
			case "globalpayments_heartland":
				$gateway = new HeartlandGateway();
				break;
			case "globalpayments_transit":
				$gateway = new TransitGateway();
				break;
			case "globalpayments_genius":
				$gateway = new GeniusGateway();
				break;
			case GpApiGateway::GATEWAY_ID:
			case GooglePayGateway::GATEWAY_ID:
			case ApplePayGateway::GATEWAY_ID:
				$gateway = new GpApiGateway();
				break;
		};

		$request = $gateway->prepare_request( self::TXN_TYPE_CAPTURE, $order );

		try {
			$response = $gateway->submit_request( $request );

			if ( "00" === $response->responseCode && "Success" === $response->responseMessage
			     || 'SUCCESS' === $response->responseCode && "CAPTURED" === $response->responseMessage ) {
				delete_post_meta( $order->get_id(), '_globalpayments_payment_action' );
				$order->add_order_note(
					"Transaction captured. Transaction ID for the capture: " . $response->transactionReference->transactionId
				);
			}

			return $response;
		} catch ( Exception $e ) {
			wp_die(
				$e->getMessage(),
				'',
				array(
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Handle online refund requests via WP Admin > WooCommerce > Edit Order > Order actions
	 *
	 * @param $order_id
	 *
	 * @return Transaction
	 * @throws Exception
	 */
	public function get_transaction_details( $order_id ) {
		$order    = wc_get_order( $order_id );
		$request  = $this->prepare_request( self::TXN_TYPE_REPORT_TXN_DETAILS, $order );
		$response = $this->submit_request( $request );

		return $response;
	}

	/**
	 * Creates the necessary request based on the transaction type
	 *
	 * @param $txn_type
	 * @param WC_Order|null $order
	 *
	 * @return Requests\RequestInterface
	 * @throws Exception
	 */
	protected function prepare_request( $txn_type, WC_Order $order = null ) {
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
		);

		if ( ! isset( $map[ $txn_type ] ) ) {
			throw new \Exception( 'Cannot perform transaction' );
		}

		$request = $map[ $txn_type ];

		return new $request(
			$this->id,
			$order,
			array_merge( array( 'gatewayProvider' => $this->get_gateway_provider() ), $this->get_backend_gateway_options() )
		);
	}

	/**
	 * Executes the prepared request
	 *
	 * @param Requests\RequestInterface $request
	 *
	 * @return Transaction
	 */
	protected function submit_request( Requests\RequestInterface $request ) {
		return $this->client->set_request( $request )->execute();
	}

	/**
	 * Reacts to the transaction response
	 *
	 * @param Requests\RequestInterface $request
	 * @param Transaction $response
	 *
	 * @return bool
	 * @throws ApiException
	 */
	protected function handle_response( Requests\RequestInterface $request, Transaction $response ) {
		if ( $response->responseCode !== '00' && 'SUCCESS' !== $response->responseCode || $response->responseMessage === 'Partially Approved' ) {
			if ( $response->responseCode === '10' || $response->responseMessage === 'Partially Approved' ) {
				try {
					$response->void()->withDescription( 'POST_AUTH_USER_DECLINE' )->execute();

					return false;
				} catch ( \Exception $e ) {
					/** om nom */
				}
			}

			throw new ApiException( $this->mapResponseCodeToFriendlyMessage( $response->responseCode ) );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		if ( '00' !== $response->responseCode && 'SUCCESS' !== $response->responseCode ) {
			$woocommerce     = WC();
			$decline_message = $this->get_decline_message( $response->responseCode );

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $decline_message, 'error' );
			} else if ( isset( $woocommerce ) && property_exists( $woocommerce, 'add_error' ) ) {
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

					if (!is_array($data)) {
						$data = [];
					}

					Transaction::fromId($response->transactionReference->transactionId)
						->reverse($data['total'])
						->execute();

					return false;
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

		if ( AbstractGateway::TXN_TYPE_AUTHORIZE !== $theorder->get_meta( '_globalpayments_payment_action' ) ) {
			return $actions;
		}
		$actions['capture_credit_card_authorization'] = 'Capture credit card authorization';

		return $actions;
	}

	public function get_order_info() {
		if ( ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		wp_send_json( [
			'error'   => false,
			'message' => $this->get_order_data(),
		] );
	}

	protected function get_order_data() {
		return array(
			'id'       => absint( get_query_var( 'order-pay' ) ),
			'amount'   => wc_format_decimal( $this->get_order_total(), 2 ),
			'currency' => get_woocommerce_currency(),
		);
	}

	/**
	 * Disable adding new cards via 'My Account', if a Digital Wallet or "Allow Card Saving" option not checked in admin.
	 *
	 * @param array $available_gateways
	 *
	 * @return array
	 */
	public function woocommerce_available_payment_gateways( $available_gateways ) {
		if ( $this->is_digital_wallet ) {
			unset( $available_gateways[ $this->id ] );
		}

		if ( 'no' === $this->get_option( 'allow_card_saving' ) ) {
			unset( $available_gateways[ $this->id ] );
		}

		return $available_gateways;
	}

	public function avs_rejection_conditions() {
		return array(
			'A' => 'A - Address matches, zip No Match',
			'N' => 'N - Neither address or zip code match',
			'R' => 'R - Retry - system unable to respond',
			'U' => 'U - Visa / Discover card AVS not supported',
			'S' => 'S - Master / Amex card AVS not supported',
			'Z' => 'Z - Visa / Discover card 9-digit zip code match, address no match',
			'W' => 'W - Master / Amex card 9-digit zip code match, address no match',
			'Y' => 'Y - Visa / Discover card 5-digit zip code and address match',
			'X' => 'X - Master / Amex card 5-digit zip code and address match',
			'G' => 'G - Address not verified for International transaction',
			'B' => 'B - Address match, Zip not verified',
			'C' => 'C - Address and zip mismatch',
			'D' => 'D - Address and zip match',
			'I' => 'I - AVS not verified for International transaction',
			'M' => 'M - Street address and postal code matches',
			'P' => 'P - Address and Zip not verified'
		);
	}

	public function cvn_rejection_conditions() {
		return array(
			'N' => 'N - Not Matching',
			'P' => 'P - Not Processed',
			'S' => 'S - Result not present',
			'U' => 'U - Issuer not certified',
			'?' => '? - CVV unrecognized'
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
		if ( $this->is_digital_wallet ) {
			return $settings;
		}
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return $settings;
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $available_gateways[ $this->id ] ) ) {
			return $settings;
		}
		foreach ( $available_gateways as $gateway ) {
			if ( $gateway instanceof AbstractGateway && ! $gateway->is_digital_wallet ) {
				$settings['enabled'] = 'no';
				add_action( 'woocommerce_sections_checkout', function () use ( $gateway ) {
					echo '<div id="message" class="error inline"><p><strong>' .
					     __( 'You can enable only one GlobalPayments gateway at a time. Please disable ' . $gateway->method_title . ' first!',
						     'globalpayments-gateway-provider-for-woocommerce'
					     ) .
					     '</strong></p></div>';
				} );

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
			wp_enqueue_script(
				'globalpayments-enforce-single-gateway',
				Plugin::get_url( '/assets/admin/js/globalpayments-enforce-single-gateway.js' )
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
			array(),
			WC()->version,
			true
		);
		wp_localize_script(
			'globalpayments-admin',
			'globalpayments_admin_params',
			array(
				'gateway_id' => $section,
			)
		);

		if ( $this->is_digital_wallet ) {
			wp_enqueue_style(
				'globalpayments-admin',
				Plugin::get_url( '/assets/admin/css/globalpayments-admin.css' ),
				array(),
				WC()->version
			);
		}
	}
}
