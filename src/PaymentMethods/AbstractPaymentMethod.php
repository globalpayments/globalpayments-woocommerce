<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods;

use GlobalPayments\Api\Entities\Exceptions\GatewayException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

abstract class AbstractPaymentMethod extends WC_Payment_Gateway implements PaymentMethodInterface {
	/**
	 * Gateway.
	 *
	 * @var GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;
	 */
	public $gateway;

	/**
	 * Payment method default title.
	 *
	 * @var string
	 */
	public $default_title;

	/**
	 * Action to perform at checkout
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
	 * Payment source.
	 *
	 * @var string
	 */
	protected $payment_source;

	public function __construct() {
		$this->has_fields = true;
		$this->supports   = array(
			'refunds',
		);

		$this->init_gateway();
		$this->configure_method_settings();
		$this->init_form_fields();
		$this->init_settings();
		$this->configure_merchant_settings();
		$this->add_hooks();
	}

	/**
	 * Sets the necessary WooCommerce payment method settings for exposing the
	 * payment method in the WooCommerce Admin.
	 *
	 * @return
	 */
	abstract public function configure_method_settings();

	/**
	 * Custom admin options to configure the payment method specific credentials, features, etc.
	 *
	 * @return array
	 */
	abstract public function get_payment_method_form_fields();

	/**
	 * Required options for proper client-side configuration.
	 *
	 * @return array
	 */
	abstract public function get_frontend_payment_method_options();

	/**
	 * Request type.
	 *
	 * @return GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface
	 */
	abstract public function get_request_type();

	/**
	 * Should be overwritten to provide additional functionality before payment gateway request.
	 *
	 * @param $request
	 * @param $order
	 */
	public function process_payment_before_gateway_request( &$request, $order ) {
		return;
	}

	/**
	 * Should be overwritten to provide additional functionality after payment gateway response is received.
	 *
	 * @param $gateway_response
	 * @param $is_successful
	 * @param $order
	 */
	public function process_payment_after_gateway_response( Transaction $gateway_response, $is_successful, \WC_Order $order ) {
		return;
	}

	/**
	 * Email address of the first-line support team.
	 *
	 * @return string
	 */
	public function get_first_line_support_email() {
		return $this->gateway->get_first_line_support_email();
	}

	/**
	 * @inheritdoc
	 */
	public function init_form_fields() {
		$this->form_fields = array_merge(
			array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable payment method', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default' => 'no',
				),
				'title'   => array(
					'title'             => esc_html__( 'Title', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'              => 'text',
					'description'       => esc_html__( 'This controls the title which the user sees during checkout.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'           => esc_html__( $this->default_title, 'globalpayments-gateway-provider-for-woocommerce' ),
					'desc_tip'          => true,
					'custom_attributes' => array( 'required' => 'required' ),
				),
			),
			$this->get_payment_method_form_fields(),
			array(
				'payment_action' => array(
					'title'       => __( 'Payment Action', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only for a delayed capture.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => AbstractGateway::TXN_TYPE_SALE,
					'desc_tip'    => true,
					'options'     => $this->get_payment_action_options(),
				),
			)
		);
	}

	/**
	 * Get supported payment actions at checkout.
	 *
	 * @return array
	 */
	public function get_payment_action_options() {
		return array(
			AbstractGateway::TXN_TYPE_SALE      => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
			AbstractGateway::TXN_TYPE_AUTHORIZE => __( 'Authorize only', 'globalpayments-gateway-provider-for-woocommerce' ),
		);
	}

	/**
	 * Sets the configurable merchant settings for use elsewhere in the class.
	 *
	 * @return
	 */
	public function configure_merchant_settings() {
		$this->title          = $this->get_option( 'title' );
		$this->enabled        = $this->get_option( 'enabled' );
		$this->payment_action = $this->get_option( 'payment_action' );

		foreach ( $this->get_payment_method_form_fields() as $key => $options ) {
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
	 *Add payment method specific hooks.
	 */
	public function add_hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );
		add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		
		// Admin refund advisory for async payments
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_refund_advisory_for_async' ), 100 );
		}
	}

	/**
	 * Enqueues payment method specific scripts.
	 *
	 * @return
	 */
	public function enqueue_payment_scripts() {
		return;
	}

	public function admin_enqueue_scripts( $hook_suffix ) {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix || 'checkout' !== $tab ) {
			return;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : null;
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
		wp_enqueue_style(
			'globalpayments-admin',
			Plugin::get_url( '/assets/admin/css/globalpayments-admin.css' ),
			array(),
			WC()->version
		);
	}

	/**
	 * @inheritdoc
	 */
	public function payment_fields() {
		$this->enqueue_payment_scripts();
		?>

		<div class="form-row form-row-wide globalpayments <?php echo esc_attr( $this->id ); ?>">
			<div id="<?php echo esc_attr( $this->id ); ?>"></div>
		</div>
		<div class="clear"></div>

		<?php
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		try {
			$request = $this->gateway->prepare_request(
					$this->get_request_type(),
					$order
			);
			$this->process_payment_before_gateway_request( $request, $order );
			$gateway_response = $this->gateway->client->submit_request( $request );
			$is_successful = $this->gateway->handle_response( $request, $gateway_response );
			$this->process_payment_after_gateway_response( $gateway_response, $is_successful, $order );

			return array(
				'result'   => $this->get_process_payment_result( $is_successful, $order ),
				'redirect' => $this->get_process_payment_redirect( $is_successful, $order ),
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			if ( $e instanceof GatewayException ) {
				throw new \Exception( esc_html( Utils::map_response_code_to_friendly_message() ) );
			}
			throw new \Exception( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->gateway->process_refund( $order_id, $amount, $reason );
	}

	/**
	 * Get payment source
	 */
	public function get_payment_source() {
		return $this->payment_source;
	}

	protected function get_process_payment_result( $is_successful, $order ) {
		return $is_successful ? 'success' : 'failure';
	}

	protected function get_process_payment_redirect( $is_successful, $order ) {
		return $is_successful ? $this->get_return_url( $order ) : false;
	}

	/**
	 * init gateway.
	 */
	protected function init_gateway() {
		$this->gateway = new GpApiGateway( true );
	}

	/**
	 * Display refund advisory for async payment methods.
	 *
	 * @param \WC_Order $order
	 * @return void
	 */
	public function display_refund_advisory_for_async( $order ): void {
		// Prevent duplicate rendering - use static variable
		static $already_rendered = array();
		
		if ( isset( $already_rendered[ $order->get_id() ] ) ) {
			return;
		}
		
		// Only show for specific async payment method IDs or when order notes indicate async payment
		$async_payment_indicators = array( 'globalpayments_bankpayment', 'globalpayments_paypal' );
		$order_payment_method = $order->get_payment_method();
		
		// Check if it's a direct async payment method
		$is_async_payment = in_array( $order_payment_method, $async_payment_indicators, true );
		
		// Also check order notes for async payment keywords (for cases where gpapi is used but it's actually async)
		if ( ! $is_async_payment && $order_payment_method === 'globalpayments_gpapi' ) {
			$order_notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
			foreach ( $order_notes as $note ) {
				if ( stripos( $note->content, 'Open Banking' ) !== false || 
				     stripos( $note->content, 'PayPal' ) !== false || 
				     stripos( $note->content, 'Awaiting' ) !== false ) {
					$is_async_payment = true;
					break;
				}
			}
		}
		
		if ( ! $is_async_payment ) {
			return;
		}

		// Only show for orders with transaction ID
		$transaction_id = $order->get_transaction_id();
		if ( empty( $transaction_id ) ) {
			return;
		}

		$show_advisory = false;

		// Check WooCommerce order status
		$order_status = $order->get_status();
		$pending_order_statuses = array( 'pending', 'on-hold', 'processing' );
		
		if ( in_array( $order_status, $pending_order_statuses, true ) ) {
			$show_advisory = true;
		}

		// Display advisory if conditions are met
		if ( $show_advisory ) {
			$already_rendered[ $order->get_id() ] = true;
			$this->render_refund_advisory_html();
		}
	}

	/**
	 * Render the HTML for refund advisory message with tooltip near refund button.
	 *
	 * @return void
	 */
	protected function render_refund_advisory_html(): void {
		/* translators: Tooltip shown on refund button for async payment methods */
		$tooltip_message = esc_js( __( 'Payment confirmation for this method may take several days. Refunds are only available after a final payment status is received. Please wait for confirmation or contact support if the delay continues.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				function addRefundButtonTooltip() {
					// Find refund button - try multiple selectors for compatibility
					var $refundButton = $('.button.refund-items').first();
					if (!$refundButton || $refundButton.length === 0) {
						$refundButton = $('button.refund-items').first();
					}
					if (!$refundButton || $refundButton.length === 0) {
						$refundButton = $('.wc-order-refund-items button').first();
					}
					
					if ($refundButton && $refundButton.length > 0) {
						// Check if tooltip hasn't been added already
						if (!$refundButton.attr('data-globalpayments-tooltip-added')) {
							// Add title attribute to the button itself
							$refundButton.attr('title', '<?php echo $tooltip_message; ?>');
							$refundButton.attr('data-globalpayments-tooltip-added', 'true');
							
							// Initialize WooCommerce tipTip on the button itself
							if (typeof $.fn.tipTip === 'function') {
								$refundButton.tipTip( {
									'attribute': 'title',
									'fadeIn':    50,
									'fadeOut':   50,
									'delay':     200
								} );
							}
							return true;
						}
					}
					return false;
				}
				
				// Try multiple times with delays
				var attempts = 0;
				var maxAttempts = 3;
				var retryInterval = setInterval(function() {
					if (addRefundButtonTooltip() || ++attempts >= maxAttempts) {
						clearInterval(retryInterval);
					}
				}, 500);
			});
		</script>
		<?php
	}
}
