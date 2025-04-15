<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Utils\GenerationUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

abstract class AbstractBuyNowPayLater extends AbstractAsyncPaymentMethod {
	/**
	 * Payment method BNPL provider. Should be overridden by individual BNPL payment methods implementations.
	 *
	 * @var string
	 */
	public $payment_method_BNPL_provider;

	public function get_request_type() {
		return AbstractGateway::TXN_TYPE_BNPL_AUTHORIZE;
	}

	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_after_checkout_validation', array(
			$this,
			'after_checkout_validation'
		), 10, 2);
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
                /* translators: %1$s: Payment initiated %3$s Transaction ID %2$s */
				esc_html__( '%1$s payment initiated with %3$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
				wc_price( $order->get_total() ),
				$gateway_response->transactionId,
				$this->payment_method_BNPL_provider
			);
			$order->add_order_note( $note_text );
			$order->set_transaction_id( $gateway_response->transactionId );

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( '_globalpayments_payment_action', $this->payment_action );
			} else {
				update_post_meta( $order_id, '_globalpayments_payment_action', $this->payment_action );
			}

			$order->save();

			// 2. Redirect the customer
			return array(
				'result'   => 'success',
				'redirect' => $gateway_response->transactionReference->bnplResponse->redirectUrl,
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			throw new \Exception( esc_html( Utils::map_response_code_to_friendly_message() ) );
		}
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
}
