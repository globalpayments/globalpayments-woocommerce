<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\TransactionInfoTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;

defined( 'ABSPATH' ) || exit;

abstract class AbstractOpenBanking extends AbstractAsyncPaymentMethod {
	use TransactionInfoTrait;

	/**
	 * Payment method OB provider. Should be overridden by individual OB payment methods implementations.
	 *
	 * @var string
	 */
	public $payment_method_openbanking_provider;

	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'woocommerce_settings_set_payment_action' ) );
	}

	/**
	 * Enqueues OB scripts from Global Payments.
	 *
	 * @return
	 */
	public function enqueue_scripts() {
		return;
	}


	/**
	 * @inheritdoc
	 */
	public function get_payment_action_options() {
		return array(
			AbstractGateway::TXN_TYPE_SALE => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
		);
	}

	/**
	 * Force payment action to `charge`.
	 *
	 * @param $settings
	 *
	 * @return mixed
	 */
	public function woocommerce_settings_set_payment_action( $settings ) {
		$settings['payment_action'] = AbstractGateway::TXN_TYPE_SALE;

		return $settings;
	}

	/**
	 * @inheritdoc
	 */
	public function get_request_type() {
		return AbstractGateway::TXN_TYPE_OB_AUTHORIZATION;
	}

	public function is_available() {
		if ( false === parent::is_available() ) {
			return false;
		}
		$currency = get_woocommerce_currency();
		$method_availability = $this->get_method_availability();
		if ( ! isset( $method_availability[ $currency ] ) ) {
			return false;
		}
		// Currency is available and no countries added in the admin panel
		if ( empty( $method_availability[ $currency ] ) ) {
			return true;
		}
		if ( WC()->cart ) {
			$customer = WC()->cart->get_customer();
			if ( ! in_array( $customer->get_billing_country(), $method_availability[ $currency ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns provider and notifications endpoints.
	 *
	 * @return array
	 */
	public function get_provider_endpoints() {
		return array(
			'provider'  => $this->payment_method_openbanking_provider,
			'returnUrl' => WC()->api_request_url( $this->id . '_return', true ),
			'statusUrl' => WC()->api_request_url( $this->id . '_status', true ),
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
				$this->payment_method_openbanking_provider
			);
			$order->add_order_note( $note_text );
			$order->set_transaction_id( $gateway_response->transactionId );
			$order->save();

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( '_globalpayments_payment_action', $this->payment_action );
			} else {
				update_post_meta( $order_id, '_globalpayments_payment_action', $this->payment_action );
			}

			// 2. Redirect the customer
			return array(
				'result'   => 'success',
				'redirect' => $gateway_response->bankPaymentResponse->redirectUrl
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			throw new \Exception( Utils::map_response_code_to_friendly_message() );
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
		$request = $this->gateway->prepare_request( AbstractGateway::TXN_TYPE_OB_AUTHORIZATION, $order );
		$settings = [
			'bank_payment_type' => $this->payment_method_openbanking_provider,
			'iban'              => $this->iban,
			'account_number'    => $this->account_number,
			'account_name'      => $this->account_name,
			'sort_code'         => $this->sort_code,
			'countries'         => $this->countries,
		];
		$request->set_request_data( array(
			'globalpayments_openbanking' => $this->get_provider_endpoints(),
			'settings' => $settings,
		) );

		$gateway_response = $this->gateway->client->submit_request( $request );

		$this->gateway->handle_response( $request, $gateway_response );

		return $gateway_response;
	}
}
