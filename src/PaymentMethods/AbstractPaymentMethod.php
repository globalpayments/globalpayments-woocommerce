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

	public function __construct() {
		$this->gateway    = new GpApiGateway( true );
		$this->has_fields = true;
		$this->supports   = array(
			'refunds',
		);

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
					'title'             => __( 'Title', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'This controls the title which the user sees during checkout.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'           => __( $this->default_title, 'globalpayments-gateway-provider-for-woocommerce' ),
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
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix || isset( $_GET['tab'] ) && 'checkout' !== $_GET['tab'] ) {
			return;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : null;
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
				throw new \Exception( Utils::map_response_code_to_friendly_message() );
			}
			throw new \Exception( $e->getMessage() );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->gateway->process_refund( $order_id, $amount, $reason );
	}

	protected function get_process_payment_result( $is_successful, $order ) {
		return $is_successful ? 'success' : 'failure';
	}

	protected function get_process_payment_redirect( $is_successful, $order ) {
		return $is_successful ? $this->get_return_url( $order ) : false;
	}
}
