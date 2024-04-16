<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;

class Paypal extends AbstractAsyncPaymentMethod {
	public const PAYMENT_METHOD_ID = 'globalpayments_paypal';

	public string $payment_method_paypal_provider = AlternativePaymentType::PAYPAL;

	/**
	 * @inheritDoc
	 */
	public function get_method_availability(): array {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function configure_method_settings() {
		$this->default_title      = __( 'Pay with PayPal', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - PayPal',
			'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to PayPal via Unified Payments Gateway',
			'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * @inheritDoc
	 */
	public function get_request_type() {
		return AbstractGateway::TXN_TYPE_PAYPAL_INITIATE;
	}

	public function get_provider_endpoints(): array {
		return array(
			'provider'  => $this->payment_method_paypal_provider,
			'returnUrl' => WC()->api_request_url( $this->id . '_return', true ),
			'statusUrl' => WC()->api_request_url( $this->id . '_status', true ),
			'cancelUrl' => WC()->api_request_url( $this->id . '_cancel', true ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function is_shipping_required() {
		return false;
	}

	/**
	 * Adds necessary gateway-specific hooks
	 *
	 * @return
	 */
	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_order_actions', array( $this, 'add_capture_order_action' ), 10, 2 );
	}

	/**
	 * Adds delayed capture functionality to the "Edit Order" screen
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function add_capture_order_action( $actions, $order ) {
		if ( $order->get_data()['payment_method'] !== self::PAYMENT_METHOD_ID ) {
			return $actions;
		}

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
	 * @inheritdoc
	 * @throws Exception
	 */
	public function process_payment( $order_id ) {
		// At this point, order should be placed in 'Pending Payment', but products should still be visible in the cart
		$order = wc_get_order( $order_id );

		try {
			// 1. Initiate the payment
			$gateway_response = $this->initiate_payment( $order );

			// Add order note  prior to customer redirect
			$note_text = sprintf(
				__( '%1$s payment initiated with %3$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
				wc_price( $order->get_total() ),
				$gateway_response->transactionId,
				ucwords($this->payment_method_paypal_provider)
			);
			$order->add_order_note( $note_text );
			$order->set_transaction_id( $gateway_response->transactionId );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( '_globalpayments_payment_action', $this->payment_action );
			} else {
				update_post_meta( $order_id, '_globalpayments_payment_action', $this->payment_action );
			}

			// 2. Redirect the customer
			return array(
				'result'   => 'success',
				'redirect' => $gateway_response->transactionReference->alternativePaymentResponse->redirectUrl,
			);
		} catch ( Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			throw new Exception( Utils::map_response_code_to_friendly_message() );
		}
	}


	/**
	 * Initiate the payment.
	 *
	 * @param WC_Order $order
	 *
	 * @throws ApiException
	 * @throws Exception
	 */
	private function initiate_payment( WC_Order $order ) {
		$request = $this->gateway->prepare_request( AbstractGateway::TXN_TYPE_PAYPAL_INITIATE, $order );

		$request->set_request_data( array(
			$this::PAYMENT_METHOD_ID => $this->get_provider_endpoints(),
			'payment_action'         => $this->payment_action,
			)
		);

		$gateway_response = $this->gateway->client->submit_request( $request );

		$this->gateway->handle_response( $request, $gateway_response );

		return $gateway_response;
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

		return [
			'id' => $params['id'] ?? '',
			'session_token' => $params['session_token'] ?? '',
			'payer_reference' => $params['payer_reference'] ?? '',
			'pasref' => $params['pasref'] ?? '',
			'action_type' => $params['action_type'] ?? '',
			'action_id' => $params['action_id'] ?? '',
		];
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
				case TransactionStatus::PENDING:
					$transaction = Transaction::fromId(
						$request->get_param('id'),
						$order->get_id(),
						PaymentMethodType::APM
					);

					$transaction->confirm( $order->get_total() )
						->withCurrency( $order->get_currency() )
						->withAlternativePaymentType( AlternativePaymentType::PAYPAL )
						->execute();
					if ( $this->payment_action == AbstractGateway::TXN_TYPE_SALE ) {
						$status = __( 'Captured', 'globalpayments-gateway-provider-for-woocommerce' );
					} else {
						$status = __( 'Authorized', 'globalpayments-gateway-provider-for-woocommerce' );
					}

					$note_text = sprintf(
						__( '%1$s amount of %2$s. Transaction ID: %3$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
						$status,
						wc_price( $order->get_total() ),
						$order->get_transaction_id()
					);
					$order->update_status( 'processing', $note_text );
					$order->payment_complete();

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
							__( 'Order ID: %s. Unexpected transaction status on returnUrl: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
							$gateway_response->orderId,
							$gateway_response->transactionStatus
						)
				);
			}
		} catch ( \Exception $e ) {
			$log_text = sprintf(
				__( 'Error completing order return with %s. %s %s', 'globalpayments-gateway-provider-for-woocommerce' ),
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
}
