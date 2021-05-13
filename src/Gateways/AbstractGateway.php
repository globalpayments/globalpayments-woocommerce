<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

defined( 'ABSPATH' ) || exit;

use Exception;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use WC_Payment_Gateway_CC;
use WC_Order;
use GlobalPayments\Api\Entities\Transaction;

use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

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
	const ENVIRONMENT_SANDBOX    = 'sandbox';

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

	public function __construct() {
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
	 * Avs Rejection Conditions
	 *
	 * @return string
	 */
	abstract public function avs_rejection_conditions();
	
	/**
	 * CVV Rejection Conditions
	 *
	 * @return string
	 */
	abstract public function cvn_rejection_conditions();

	/**
	 * Get the current gateway provider
	 *
	 * @return string
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
	public function woocommerce_credit_card_form_fields( $default_fields ) {
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

		// Global Payments styles for client-side tokenization
		$css_style = Plugin::get_url( '/assets/frontend/css/globalpayments-secure-payment-fields.css' );
		/**
		 * Allow iframe styling according to theme
		 *
		 * @param $css_style CSS stylesheet
		 */
		$css_style = apply_filters( 'globalpayments_secure_payment_fields', $css_style );
		wp_enqueue_style(
			'globalpayments-secure-payment-fields',
			$css_style,
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
		wp_enqueue_script(
			'globalpayments-secure-payment-fields',
			Plugin::get_url( '/assets/frontend/js/globalpayments-secure-payment-fields.js' ),
			array( 'globalpayments-secure-payment-fields-lib', 'jquery' ),
			WC()->version,
			true
		);
		wp_localize_script(
			'globalpayments-secure-payment-fields',
			'globalpayments_secure_payment_fields_params',
			array(
				'id'              => $this->id,
				'gateway_options' => $this->get_frontend_gateway_options(),
				'field_options'   => $this->secure_payment_fields(),
			)
		);

		// Global Payments scripts for handling 3DS
		if ( GatewayProvider::GP_API !== $this->gateway_provider ) {
			return;
		}

		wp_enqueue_script(
			'globalpayments-threedsecure-lib',
			Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
			. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
			array( 'globalpayments-secure-payment-fields-lib' ),
			WC()->version,
			true
		);
		wp_localize_script(
			'globalpayments-secure-payment-fields',
			'globalpayments_secure_payment_threedsecure_params',
			array(
				'threedsecure'    => array(
					'methodNotificationUrl'     => WC()->api_request_url( 'globalpayments_threedsecure_methodnotification' ),
					'challengeNotificationUrl'  => WC()->api_request_url( 'globalpayments_threedsecure_challengenotification' ),
					'checkEnrollmentUrl'        => WC()->api_request_url( 'globalpayments_threedsecure_checkenrollment' ),
					'initiateAuthenticationUrl' => WC()->api_request_url( 'globalpayments_threedsecure_initiateauthentication' ),
				),
				'order'           => array (
					'amount'          => $this->get_session_amount(),
					'currency'        => get_woocommerce_currency(),
					'billingAddress'  => $this->get_billing_address(),
					'shippingAddress' => $this->get_shipping_address(),
					'customerEmail'   => $this->get_customer_email(),
				),
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
					'title'       => __( 'Title', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => __( 'Credit Card', 'globalpayments-gateway-provider-for-woocommerce' ),
					'desc_tip'    => true,
				),
			),
			$this->get_gateway_form_fields(),
			array(
				'payment_action'    => array(
					'title'       => __( 'Payment Action', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to capture funds immediately, authorize payment only for a delayed capture, or verify and capture when the order ships.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => 'sale',
					'desc_tip'    => true,
					'options'     => array(
						self::TXN_TYPE_SALE      => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
						self::TXN_TYPE_AUTHORIZE => __( 'Authorize only', 'globalpayments-gateway-provider-for-woocommerce' ),
						self::TXN_TYPE_VERIFY    => __( 'Verify only', 'globalpayments-gateway-provider-for-woocommerce' ),
					),
				),
				'allow_card_saving' => array(
					'title'       => __( 'Allow Card Saving', 'globalpayments-gateway-provider-for-woocommerce' ),
					'label'       => __( 'Allow Card Saving', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'checkbox',
					'description' => sprintf(
						/* translators: %s: Email address of support team */
						__( 'Note: to use the card saving feature, you must have multi-use token support enabled on your account. Please contact <a href="mailto:%s?Subject=WooCommerce%%20Transaction%%20Descriptor%%20Option">support</a> with any questions regarding this option.', 'globalpayments-gateway-provider-for-woocommerce' ),
						$this->get_first_line_support_email()
					),
					'default'     => 'no',
				),
				'txn_descriptor'    => array(
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
						'maxlength' => 18,
					),
				),
			)
		);
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
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( 'no' === $this->enabled ) {
			return;
		}
		// hooks only active when the gateway is enabled
		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'woocommerce_credit_card_form_fields' ) );

		if ( is_add_payment_method_page() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'tokenization_script' ) );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'woocommerce_available_payment_gateways') );
		}
	}

	/**
	 * Handle payment functions
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order         = new WC_Order( $order_id );
		$request       = $this->prepare_request( $this->payment_action, $order );
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}

	/**
	 * Handle adding new cards via 'My Account'
	 *
	 * @return array
	 */
	public function add_payment_method() {
		$request = $this->prepare_request( self::TXN_TYPE_VERIFY );
		$redirect = wc_get_endpoint_url( 'payment-methods' );

		try {
			$response = $this->submit_request( $request );
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
	 * @param null|number $amount
	 * @param string $reason
	 *
	 * @return array
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$details                = $this->get_transaction_details( $order_id );
		$is_order_txn_id_active = $this->is_transaction_active( $details );
		$txn_type               = $is_order_txn_id_active ? self::TXN_TYPE_REVERSAL : self::TXN_TYPE_REFUND;

		$order         = new WC_Order( $order_id );
		$request       = $this->prepare_request( $txn_type, $order );
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ($is_successful) {
			$note_text = sprintf(
				'%s%s was reversed or refunded. Transaction ID: %s ',
				get_woocommerce_currency_symbol(), $amount, $response->transactionReference->transactionId
			);

			$order->add_order_note($note_text);
		}

		return $is_successful;
	}

	/**
	 * Handle capture auth requests via WP Admin > WooCommerce > Edit Order
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public static function capture_credit_card_authorization( $order_id ) {
		$order    = new WC_Order( $order_id );

		switch ($order->get_payment_method()) {
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
				$gateway = new GpApiGateway();
				break;
		};

		$request  = $gateway->prepare_request( self::TXN_TYPE_CAPTURE, $order );

		try {
			$response = $gateway->submit_request( $request );

			if ( "00" === $response->responseCode && "Success" === $response->responseMessage
				|| 'SUCCESS' === $response->responseCode && "CAPTURED" === $response->responseMessage ) {
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
	 * @param int $order_id
	 *
	 * @return TransactionSummary
	 */
	public function get_transaction_details( $order_id ) {
		$order    = new WC_Order( $order_id );
		$request  = $this->prepare_request( self::TXN_TYPE_REPORT_TXN_DETAILS, $order );
		$response = $this->submit_request( $request );

		return $response;
	}

	/**
	 * Creates the necessary request based on the transaction type
	 *
	 * @param WC_Order $order
	 * @param string $txn_type
	 *
	 * @return Requests\RequestInterface
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
	 */
	protected function handle_response( Requests\RequestInterface $request, Transaction $response ) {
		if ($response->responseCode !== '00' && 'SUCCESS' !== $response->responseCode || $response->responseMessage === 'Partially Approved') {
			if ($response->responseCode === '10' || $response->responseMessage === 'Partially Approved') {
				try {
					$response->void()->withDescription('POST_AUTH_USER_DECLINE')->execute();
					return false;
				} catch (\Exception $e) { /** om nom */ }
			}

			throw new ApiException($this->mapResponseCodeToFriendlyMessage($response->responseCode));
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		if ( '00' !== $response->responseCode  && 'SUCCESS' !== $response->responseCode ) {
			$woocommerce = WC();
			$decline_message = $this->get_decline_message($response->responseCode);

			if (function_exists('wc_add_notice')) {
				wc_add_notice($decline_message, 'error');
			} else if (isset($woocommerce) && property_exists($woocommerce, 'add_error')) {
				$woocommerce->add_error($decline_message);
			}

			return false;
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
	public static function addCaptureOrderAction( $actions )
    {
		global $theorder;

		if ( AbstractGateway::TXN_TYPE_AUTHORIZE !== $theorder->get_meta('_globalpayments_payment_action') ) {
			return $actions;
		}
        $actions['capture_credit_card_authorization'] = 'Capture credit card authorization';
        return $actions;
    }

	/**
	 * Disable adding new cards via 'My Account', if "Allow Card Saving" option not checked in admin.
	 *
	 * @param array $available_gateways
	 * @return array
	 */
    public function woocommerce_available_payment_gateways( $available_gateways ) {
		if ( 'no' === $this->get_option( 'allow_card_saving' ) ) {
			unset( $available_gateways[ $this->id ]);
		}

		return $available_gateways;
	}

}
