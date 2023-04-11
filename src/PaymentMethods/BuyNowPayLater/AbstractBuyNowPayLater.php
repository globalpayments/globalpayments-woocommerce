<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Utils\GenerationUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\TransactionInfoTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

abstract class AbstractBuyNowPayLater extends WC_Payment_Gateway {
	use TransactionInfoTrait;

	/**
	 * Payment method BNPL provider. Should be overridden by individual BNPL payment methods implementations.
	 *
	 * @var string
	 */
	public $payment_method_BNPL_provider;

	/**
	 * Payment method default title.
	 *
	 * @var string
	 */
	public $default_title;

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
		$this->enqueue_scripts();
	}

	public function add_hooks() {
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_order_received_text' ) );
		/**
		 * The WooCommerce API allows plugins make a callback to a special URL that will then load the specified class (if it exists)
		 * and run an action. This is also useful for gateways that are not initialized.
		 */
		add_action( 'woocommerce_api_' . $this->id . '_return', array(
			$this,
			'process_bnpl_return'
		) );
		add_action( 'woocommerce_api_' . $this->id . '_status', array(
			$this,
			'process_bnpl_status'
		) );
		add_action( 'woocommerce_api_' . $this->id . '_cancel', array(
			$this,
			'process_bnpl_cancel'
		) );

		add_action( 'woocommerce_after_checkout_validation', array(
			$this,
			'after_checkout_validation'
		), 10, 2);

		// Admin View Transaction Info hooks
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'transaction_info_modal' ), 99 );
		}
		add_action( 'woocommerce_api_globalpayments_get_transaction_info', array( $this, 'get_transaction_info' ) );
	}

	/**
	 * Enqueues BNPL scripts from Global Payments.
	 *
	 * @return
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || wp_script_is( 'globalpayments-bnpl', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'globalpayments-bnpl',
			Plugin::get_url( '/assets/frontend/js/globalpayments-bnpl.js' ),
			array( 'jquery', 'jquery-blockui' ),
			Plugin::VERSION,
			true
		);
	}

	/**
	 * Sets the necessary WooCommerce payment method settings for exposing the
	 * gateway in the WooCommerce Admin.
	 *
	 * @return
	 */
	abstract public function configure_method_settings();

	/**
	 * Custom admin options to configure the gateway-specific credentials, features, etc.
	 *
	 * @return array
	 */
	abstract public function get_gateway_form_fields();

	/**
	 * Currencies and countries this payment method is allowed for.
	 *
	 * @return array
	 */
	abstract public function get_method_availability();

	/**
	 * States whether the shipping country should be considered for method availability.
	 *
	 * @return bool
	 */
	public function is_shipping_required() {
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function is_available() {
		if ( false === parent::is_available() ) {
			return false;
		}
		$currency = get_woocommerce_currency();
		$method_availability = $this->get_method_availability();
		if ( ! isset( $method_availability[ $currency ] ) ) {
			return false;
		}
		if ( WC()->cart ) {
			$customer = WC()->cart->get_customer();
			if ( $this->is_shipping_required() ) {
				if ( ! in_array( $customer->get_billing_country(), $method_availability[ $currency ] )
				     || ! in_array( $customer->get_shipping_country(), $method_availability[ $currency ] ) ) {
					return false;
				}
			} elseif ( ! in_array( $customer->get_billing_country(), $method_availability[ $currency ] ) ) {
				return false;
			}
		}

		return true;
	}

	public function after_checkout_validation( $data, $wp_error ) {
		if ( $this->id != $data['payment_method'] ) {
			return;
		}
		if ( empty( $data['billing_postcode'] ) && ( empty( $wp_error->errors['billing_postcode_required'] ) || empty( $wp_error->errors['billing_postcode_validation'] ) ) ) {
			$wp_error->add ( 'billing_postcode', __( '<strong>Billing ZIP Code</strong> is a required field for this payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		if ( WC()->cart->needs_shipping() && empty( $data['shipping_postcode'] ) && ( empty( $wp_error->errors['shipping_postcode_required'] ) || empty( $wp_error->errors['shipping_postcode_validation'] ) ) ) {
			$wp_error->add ( 'shipping_postcode', __( '<strong>Shipping ZIP Code</strong> is a required field for this payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		if ( empty( $data['billing_phone'] ) && ( empty( $wp_error->errors['billing_phone_required'] ) || empty( $wp_error->errors['billing_phone_validation'] ) ) ) {
			$wp_error->add ( 'billing_phone', __( '<strong>Phone</strong> is a required field for this payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
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
					'label'   => __( 'Enable Gateway', 'globalpayments-gateway-provider-for-woocommerce' ),
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
			$this->get_gateway_form_fields(),
			array(
				'payment_action' => array(
					'title'       => __( 'Payment Action', 'globalpayments-gateway-provider-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only for a delayed capture.', 'globalpayments-gateway-provider-for-woocommerce' ),
					'default'     => AbstractGateway::TXN_TYPE_SALE,
					'desc_tip'    => true,
					'options'     => array(
						AbstractGateway::TXN_TYPE_SALE      => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
						AbstractGateway::TXN_TYPE_AUTHORIZE => __( 'Authorize only', 'globalpayments-gateway-provider-for-woocommerce' ),
					),
				),
			)
		);
	}

	/**
	 * Sets the configurable merchant settings for use elsewhere in the class.
	 *
	 * @return
	 */
	public function configure_merchant_settings() {
		$this->title             = $this->get_option( 'title' );
		$this->enabled           = $this->get_option( 'enabled' );
		$this->payment_action    = $this->get_option( 'payment_action' );

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
	 * Returns provider and notifications endpoints.
	 *
	 * @return array
	 */
	public function get_provider_endpoints() {
		return array(
			'provider'  => $this->payment_method_BNPL_provider,
			'returnUrl' => WC()->api_request_url( $this->id . '_return', true ),
			'statusUrl' => WC()->api_request_url( $this->id . '_status', true ),
			'cancelUrl' => WC()->api_request_url( $this->id . '_cancel', true ),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment( $order_id ) {
		// At this point, order should be placed in 'Pending Payment', but products should still be visible in the cart
		$order = wc_get_order( $order_id );

		try {
			// 1. Initiate the payment
			$gateway_response = $this->initiate_payment( $order );

			// Add order note  prior to customer redirect
			$note_text = sprintf(
				'%1$s %2$s %4$s. Transaction ID: %3$s.',
				wc_price( $order->get_total() ),
				__( 'payment initiated with', 'globalpayments-gateway-provider-for-woocommerce' ),
				$gateway_response->transactionId,
				$this->payment_method_BNPL_provider
			);
			$order->add_order_note( $note_text );
			$order->set_transaction_id( $gateway_response->transactionId );
			$order->save();

			update_post_meta( $order_id, '_globalpayments_payment_action', $this->payment_action );

			// 2. Redirect the customer
			return array(
				'result'   => 'success',
				'redirect' => $gateway_response->transactionReference->bnplResponse->redirectUrl,
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			throw new \Exception( Utils::map_response_code_to_friendly_message() );
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->gateway->process_refund( $order_id, $amount, $reason );
	}

	/**
	 * Initiate the payment.
	 *
	 * @param WC_Order $order
	 *
	 * @throws \GlobalPayments\Api\Entities\Exceptions\ApiException
	 */
	private function initiate_payment( WC_Order $order ) {
		$request = $this->gateway->prepare_request( AbstractGateway::TXN_TYPE_BNPL_AUTHORIZE, $order );
		$request->set_request_data( array(
			'globalpayments_bnpl' => $this->get_provider_endpoints(),
		) );

		$gateway_response = $this->gateway->client->submit_request( $request );

		$this->gateway->handle_response( $request, $gateway_response );

		return $gateway_response;
	}

	/**
	 * Handle customer redirect URL.
	 */
	public function process_bnpl_return() {
		$request = Utils::get_request();

		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			switch( $gateway_response->transactionStatus ) {
				case TransactionStatus::INITIATED:
				case TransactionStatus::PREAUTHORIZED:
					wp_safe_redirect( $order->get_checkout_order_received_url() );
					break;
				case TransactionStatus::DECLINED:
				case 'FAILED':
					wc_add_notice( Utils::map_response_code_to_friendly_message( $gateway_response->transactionStatus ), 'error' );
					wp_safe_redirect( wc_get_checkout_url() );
					break;
				default:
					throw new \Exception(
						'Order ID: ' . $gateway_response->orderId . '. Unexpected transaction status on returnUrl: ' . $gateway_response->transactionStatus
					);
			}
		} catch ( \Exception $e ) {
			$log_text = sprintf(
				'Error completing order return with ' . $this->payment_method_BNPL_provider . '. %s %s',
				$e->getMessage(),
				print_r( $request->get_params(), true )
			);
			wc_get_logger()->error( $log_text );

			if ( empty( $order ) ) {
				$order = new WC_Order();
				wp_safe_redirect( add_query_arg( $this->id, 'error', $order->get_checkout_order_received_url() ) );
			} else {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
			}
		}
		exit();
	}

	/**
	 * Handle status Update URL.
	 */
	public function process_bnpl_status() {
		$request = Utils::get_request();
		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			switch( $request->get_param( 'status' ) ) {
				case TransactionStatus::PREAUTHORIZED:
					$note_text = sprintf(
						'%1$s %2$s. Transaction ID: %3$s.',
						wc_price( $order->get_total() ),
						__( 'authorized', 'globalpayments-gateway-provider-for-woocommerce' ),
						$order->get_transaction_id()
					);
					$order->update_status( 'processing', $note_text );

					if ( $this->payment_action == AbstractGateway::TXN_TYPE_SALE ) {
						AbstractGateway::capture_credit_card_authorization( $order );
					}
					$order->payment_complete();
					break;
				case TransactionStatus::DECLINED:
				case 'FAILED':
					$note_text = sprintf(
						'%1$s %2$s. Transaction ID: %3$s.',
						wc_price( $order->get_total() ),
						__( 'payment failed/declined', 'globalpayments-gateway-provider-for-woocommerce' ),
						$order->get_transaction_id()
					);
					$order->update_status( 'failed', $note_text );
					break;
				default:
					throw new \Exception(
						'Order ID: ' . $gateway_response->orderId . '. Unexpected transaction status on statusUrl: ' . $request->get_param( 'status' )
					);
			}
		} catch ( \Exception $e ) {
			$log_text = sprintf(
				'Error completing order status with ' . $this->payment_method_BNPL_provider . '. %s %s',
				$e->getMessage(),
				print_r( $request->get_body(), true )
			);
			wc_get_logger()->error( $log_text );
		}
		exit();
	}

	/**
	 * Handle customer cancel URL.
	 */
	public function process_bnpl_cancel() {
		$request = Utils::get_request();
		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			$note_text = sprintf(
				'%1$s %2$s. Transaction ID: %3$s.',
				wc_price( $order->get_total() ),
				__( 'payment canceled by customer', 'globalpayments-gateway-provider-for-woocommerce' ),
				$order->get_transaction_id()
			);
			$order->update_status( 'cancelled', $note_text );
		} catch ( \Exception $e ) {
			$log_text = sprintf(
				'Error completing order cancel with ' . $this->payment_method_BNPL_provider . '. %s %s',
				$e->getMessage(),
				print_r( $request->get_params(), true )
			);
			wc_get_logger()->error( $log_text );
		}

		wp_safe_redirect( wc_get_checkout_url() );

		exit();
	}

	/**
	 * Get WooCommerce order associated with the order ID from Transaction Summary.
	 *
	 * @param TransactionSummary $gateway_response
	 *
	 * @return bool|WC_Order|\WC_Order_Refund
	 * @throws \Exception
	 */
	private function get_order( TransactionSummary $gateway_response ) {
		$order = wc_get_order( $gateway_response->orderId );
		if ( false === $order || ! ( $order instanceof WC_Order ) ) {
			throw new \Exception( __( 'Order ID: ' . $gateway_response->orderId . '. Order not found.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		if ( $this->id != $order->get_payment_method() ) {
			throw new \Exception( __( 'Order ID: ' . $gateway_response->orderId . '. Payment method code changed. Expected ' . $this->id . ', but found ' . $order->get_payment_method() ) );
		}

		if ( $gateway_response->transactionId !== $order->get_transaction_id() ) {
			throw new \Exception( __( 'Order ID: ' . $gateway_response->orderId . '. Transaction ID changed. Expected ' . $gateway_response->transactionId . ', but found ' . $order->get_transaction_id(), 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		return $order;
	}

	/**
	 * Validate the BNPL request message by checking:
	 *
	 * 1) the signature of the notification message
	 * 2) transaction ID is present in the message
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function validate_request( \WP_REST_Request $request ) {
		$request_method = $request->get_method();
		switch ( $request_method ) {
			case 'GET':
				$xgp_signature = $request->get_param( 'X-GP-Signature' );
				$params = $request->get_query_params();
				unset( $params['X-GP-Signature'] );
				$to_hash = http_build_query( $params );
				break;
			case 'POST':
				$xgp_signature = $request->get_header( 'x_gp_signature' );
				$to_hash = $request->get_body();
				break;
			default:
				throw new \Exception( __( 'This request method is not supported.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$gen_signature = GenerationUtils::generateXGPSignature( $to_hash, $this->gateway->get_credential_setting( 'app_key' ) );

		if ( $xgp_signature !== $gen_signature ) {
			throw new \Exception( __( 'Invalid request signature.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Display a message to the user if unable to match notifications requests with an order.
	 *
	 * @param $text
	 *
	 * @return string
	 */
	public function thankyou_order_received_text( $text ) {
		if ( isset( $_GET[ $this->id ] ) ) {
			return __( 'Thank you. Your order has been received, but we have encountered an issue when redirecting back. Please contact us for assistance.', 'globalpayments-gateway-provider-for-woocommerce' );
		}

		return $text;
	}
}
