<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Utils\GenerationUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\TransactionInfoTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;

defined( 'ABSPATH' ) || exit;

abstract class AbstractAsyncPaymentMethod extends AbstractPaymentMethod implements AsyncPaymentMethodInterface {
	use TransactionInfoTrait;

	public function __construct() {
		parent::__construct();

		$this->enqueue_scripts();
	}
	/**
	 * Currencies and countries this payment method is allowed for.
	 *
	 * @return array
	 */
	abstract public function get_method_availability();

	public function add_hooks() {
		parent::add_hooks();

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );

			add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}

		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_order_received_text' ) );

		/**
		* The WooCommerce API allows plugins make a callback to a special URL that will then load the specified class (if it exists)
		* and run an action. This is also useful for gateways that are not initialized.
		*/
		add_action( 'woocommerce_api_' . $this->id . '_return', array(
			$this,
			'process_async_return'
		) );
		add_action( 'woocommerce_api_' . $this->id . '_status', array(
			$this,
			'process_async_status'
		) );
		add_action( 'woocommerce_api_' . $this->id . '_cancel', array(
			$this,
			'process_async_cancel'
		) );

		// Admin View Transaction Info hooks
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'transaction_info_modal' ), 99 );
		}
		add_action( 'woocommerce_api_globalpayments_get_transaction_info', array( $this, 'get_transaction_info' ) );
	}

	/**
	 * Enqueues BNPL scripts from Global Payments.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() || wp_script_is( 'globalpayments-async-payment-method', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'globalpayments-async-payment-method',
			Plugin::get_url( '/assets/frontend/js/globalpayments-async-payment-method.js' ),
			array( 'jquery', 'jquery-blockui' ),
			Plugin::VERSION,
			true
		);
	}

	/**
	 * Get WooCommerce order associated with the order ID from Transaction Summary.
	 *
	 * @param TransactionSummary $gateway_response
	 *
	 * @return bool|WC_Order|\WC_Order_Refund
	 * @throws \Exception
	 */
	protected function get_order( TransactionSummary $gateway_response ) {
		$order = wc_get_order( $gateway_response->orderId );
		if ( false === $order || ! ( $order instanceof WC_Order ) ) {
			throw new \Exception(
				sprintf(
                    /* translators: %s: Order ID */
					esc_html__( 'Order ID: %s. Order not found.', 'globalpayments-gateway-provider-for-woocommerce' ),
					esc_html($gateway_response->orderId)
				)
			);
		}

		if ( $this->id != $order->get_payment_method() ) {
			throw new \Exception(
				sprintf(
                    /* translators: %1$s, %2$s, %3$s: Order ID */
					esc_html__(
                        'Order ID: %1$s. Payment method code changed. Expected %2$s, but found %3$s',
                        'globalpayments-gateway-provider-for-woocommerce'
                    ),
					esc_html( $gateway_response->orderId ),
					esc_html( $this->id ),
					esc_html( $order->get_payment_method() )
				)
			);
		}

		if ( $gateway_response->transactionId !== $order->get_transaction_id() ) {
			throw new \Exception(
				sprintf(
                /* translators: %1$s, %2$s, %3$s: Transaction ID */
					esc_html__(
                        'Order ID: %1$s. Transaction ID changed. Expected %2$s, but found %3$s',
                        'globalpayments-gateway-provider-for-woocommerce'
                    ),
					esc_html( $gateway_response->orderId ),
					esc_html( $gateway_response->transactionId ),
					esc_html( $order->get_transaction_id() )
				)
			);
		}

		return $order;
	}

	/**
	 * Validate the request message by checking:
	 *
	 * 1) the signature of the notification message
	 * 2) transaction ID is present in the message
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function validate_request( \WP_REST_Request $request ) {
		$request_method = $request->get_method();
		switch ( $request_method ) {
			case 'GET':
				$xgp_signature = $request->get_param( 'X-GP-Signature' );
				$to_hash = http_build_query( $this->get_query_params($request) );
				break;
			case 'POST':
				$xgp_signature = $request->get_header( 'x_gp_signature' );
				$to_hash = $request->get_body();
				break;
			default:
				throw new \Exception( esc_html__( 'This request method is not supported.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$gen_signature = GenerationUtils::generateXGPSignature( $to_hash, $this->gateway->get_credential_setting( 'app_key' ) );

		if ( $xgp_signature !== $gen_signature ) {
			throw new \Exception( esc_html__( 'Invalid request signature.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Returns query parameters
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return array
	 */
	protected function get_query_params( \WP_REST_Request $request ): array {
		$params = $request->get_query_params();
		unset( $params['X-GP-Signature'] );

		return $params;
	}

	/**
	 * Handle customer redirect URL.
	 */
	public function process_async_return() {
		$request = Utils::get_request();

		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			switch( $gateway_response->transactionStatus ) {
				case TransactionStatus::INITIATED:
				case TransactionStatus::PREAUTHORIZED:
				case TransactionStatus::CAPTURED:
					wp_safe_redirect( $order->get_checkout_order_received_url() );
					break;
				case TransactionStatus::DECLINED:
				case 'FAILED':
					$this->cancel_order( $order );
					wp_safe_redirect( wc_get_checkout_url() );
					break;
				default:
					throw new \Exception(
						sprintf(
                            /* translators: %1$s Order ID %2$s: Return Url */
							esc_html__(
                                'Order ID: %1$s. Unexpected transaction status on returnUrl: %2$s',
                                'globalpayments-gateway-provider-for-woocommerce'
                            ),
							$gateway_response->orderId,
							$gateway_response->transactionStatus
						)
					);
			}
		} catch ( \Exception $e ) {
			$log_text = sprintf(
                /* translators: %1$s, %2$s, %3$s: Error completing order  */
				esc_html__( 'Error completing order return with %1$s. %2$s %3$s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$this->id,
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
	public function process_async_status() {
		$request = Utils::get_request();
		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			switch( $request->get_param( 'status' ) ) {
				case TransactionStatus::PREAUTHORIZED:
					$note_text = sprintf(
                        /* translators: %1$s: Authorized amount %2$sTransaction ID */
						esc_html__( 'Authorized amount of %1$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
						wc_price( $order->get_total() ),
						$order->get_transaction_id()
					);
					$order->update_status( 'processing', $note_text );

					if ( $this->payment_action == AbstractGateway::TXN_TYPE_SALE ) {
						AbstractGateway::capture_credit_card_authorization( $order );
					}
					$order->payment_complete();
					break;
				case TransactionStatus::CAPTURED:
					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$order->add_meta_data( '_globalpayments_payment_captured', 'is_captured', true );
					} else {
						add_post_meta( $order->get_id(), '_globalpayments_payment_captured', 'is_captured', true );
					}

					$note_text = sprintf(
                        /* translators: %1$s: Captured amount %2$s Transaction ID */
						esc_html__( 'Captured amount of %1$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
						wc_price( $order->get_total() ),
						$order->get_transaction_id()
					);
					$order->update_status( 'processing', $note_text );
					$order->payment_complete();
					break;
				case TransactionStatus::DECLINED:
				case 'FAILED':
					$this->cancel_order( $order );
					break;
				default:
					throw new \Exception(
						sprintf(
                            /* translators: %1$s: Unexpected transaction %2$s Status Url */
							esc_html__(
                                'Order ID: %1$s. Unexpected transaction status on statusUrl: %2$s',
                                'globalpayments-gateway-provider-for-woocommerce'
                            ),
							$gateway_response->orderId,
							$request->get_param( 'status' )
						)
					);
			}
		} catch ( \Exception $e ) {
			$log_text = sprintf(
                /* translators: %1$s: Order status %2$s %3$s */
				esc_html__( 'Error completing order status with %1$s. %2$s %3$s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$this->id,
				$e->getMessage(),
				print_r( $request->get_body(), true )
			);
			wc_get_logger()->error( $log_text );
		}
		exit();
	}

	/**
	 * @param $order
	 * @return void
	 */
	protected function cancel_order( $order ) {
		$note_text = sprintf(
            /* translators: %1$s: Payment %2$s Transaction ID */
			esc_html__( 'Payment of %1$s declined/failed. Transaction ID: %2$s', 'globalpayments-gateway-provider-for-woocommerce' ),
			wc_price( $order->get_total() ),
			$order->get_transaction_id()
		);
		$order->update_status( 'cancelled', $note_text );
	}

	/**
	 * Handle customer cancel URL.
	 */
	public function process_async_cancel() {
		$request = Utils::get_request();
		try {
			$this->validate_request( $request );

			$gateway_response = $this->gateway->get_transaction_details_by_txn_id( $request->get_param( 'id' ) );
			$order = $this->get_order( $gateway_response );

			$note_text = sprintf(
                /* translators: %1$s: Payment canceled %2$s Transaction ID */
				esc_html__( '%1$s payment canceled by customer. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
				wc_price( $order->get_total() ),
				$order->get_transaction_id()
			);
			$order->update_status( 'cancelled', $note_text );
		} catch ( \Exception $e ) {
			$log_text = sprintf(
                /* translators: %1$s: Completing order cancel %2$s %3$s */
				esc_html__( 'Error completing order cancel with %1$s. %2$s %3$s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$this->id,
				$e->getMessage(),
				print_r( $request->get_params(), true )
			);
			wc_get_logger()->error( $log_text );
		}

		wp_safe_redirect( wc_get_checkout_url() );

		exit();
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

	/**
	 * States whether the shipping country should be considered for method availability.
	 *
	 * @return bool
	 */
	public function is_shipping_required() {
		return false;
	}

	public function get_payment_method_form_fields() {
		return array();
	}
	public function get_frontend_payment_method_options() {
		return array();
	}
}
