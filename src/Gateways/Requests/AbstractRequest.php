<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;

defined( 'ABSPATH' ) || exit;

abstract class AbstractRequest implements RequestInterface {
	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	public $gateway_id;

	/**
	 * Current WooCommerce order object
	 *
	 * @var WC_Order
	 */
	public $order;

	/**
	 * Gateway config
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * POST request data
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Instantiates a new request
	 *
	 * @param string $gateway_id
	 * @param WC_Order $order
	 * @param array $config
	 */
	public function __construct( $gateway_id, WC_Order $order = null, array $config = array() ) {
		$this->gateway_id = $gateway_id;
		$this->order      = $order;
		$this->config     = $config;

		$this->data = $this->get_request_data();

		if ( empty( $this->order ) && ! empty( $this->data->order->id ) ) {
			$order = wc_get_order( $this->data->order->id );
			if ( ! empty( $order ) ) {
				$this->order = $order;
			}
		}
	}

	/**
	 * Retrieve Card Holder Name either from Hosted Fields or Billing Address.
	 *
	 * @param $customer_name
	 *
	 * @return mixed
	 */
	private function get_card_holder_name( $customer_name ) {
		if ( is_array( $this->data ) ) {
			$gatewayData = $this->data[ $this->gateway_id ] ?? null;
			if ( ! empty( $gatewayData['token_response'] ) ) {
				$data = json_decode( stripslashes( $gatewayData['token_response'] ) );
			} elseif ( ! empty( $gatewayData[ 'payment_data' ] ) ) {
				$token_response = Utils::get_data_from_payment_data( $gatewayData[ 'payment_data' ], 'token_response' );
				$data = $token_response ? json_decode( stripslashes( $token_response ) ) : null;
			}
		}

		return $data->details->cardholderName ?? $customer_name;
	}

	public function get_default_args() {
		return array(
			RequestArg::SERVICES_CONFIG  => $this->config,
			RequestArg::TXN_TYPE         => $this->get_transaction_type(),
			RequestArg::BILLING_ADDRESS  => null !== $this->order ? array(
				'streetAddress1' => $this->order->get_billing_address_1(),
				'city'           => $this->order->get_billing_city(),
				'state'          => $this->order->get_billing_state(),
				'postalCode'     => $this->order->get_billing_postcode(),
				'country'        => $this->order->get_billing_country(),
			) : null,
			RequestArg::CARD_HOLDER_NAME =>
				$this->get_card_holder_name(
					null !== $this->order ? $this->order->get_formatted_billing_full_name() : null
				),
			RequestArg::ORDER_ID => $this->get_order_identifier()
		);
	}

	/**
	 * Get order identifier with retry suffix if needed
	 *
	 * @return string|null
	 */
	protected function get_order_identifier(): ?string {
		if ( null === $this->order ) {
			return null;
		}
		
		$order_number = (string) $this->order->get_order_number();
		
		// For Genius gateway only, handle invoice number with 8 character limit
		if ( strpos( $this->gateway_id, 'genius' ) !== false ) {
			// For admin pay order, add unique suffix to prevent duplicate transactions
			// Check if this is an admin context (Pay for Order functionality)
			$is_admin_pay_order = is_admin() && current_user_can( 'edit_shop_orders' );
			
			// Also check POST data for additional confirmation
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in WooCommerce checkout/pay-order handling
			if ( ! $is_admin_pay_order && ! empty( $_POST ) ) {
				$is_admin_pay_order = isset( $_POST['woocommerce_globalpayments_pay'] );
			}
			
			if ( $is_admin_pay_order ) {
				// Use a higher-entropy suffix to reduce collisions on rapid retries
				// while keeping the full Genius invoice number within 8 characters.
				$unique_id = strtoupper( substr( hash( 'crc32b', microtime( true ) . wp_rand() ), 0, 5 ) );
				
				// Truncate order number if needed to fit: 2 chars of order + 'R' + 5 chars = max 8 chars
				$max_order_length = 2; // Leave room for 'R' + 5 character retry suffix
				if ( strlen( $order_number ) > $max_order_length ) {
					$order_number = substr( $order_number, 0, $max_order_length );
				}
				
				$order_number .= 'R' . $unique_id;
			} else {
				// For Genius gateway, ensure order number doesn't exceed 8 characters
				if ( strlen( $order_number ) > 8 ) {
					$order_number = substr( $order_number, 0, 8 );
				}
			}
		}
		
		return $order_number;
	}

	public function get_request_data( $key = null ) {
		if ( null === $key ) {
			if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) ) {
				return null;
			}
			if ( 'application/json' === $_SERVER['CONTENT_TYPE'] ) {
				return json_decode( file_get_contents( 'php://input' ) );
			}
			// WooCommerce should verify nonce during its checkout handling
			// phpcs:ignore WordPress.Security.NonceVerification
			return wc_clean( $_POST );
		}
		if ( is_array( $this->data ) && isset( $this->data[ $key ] ) ) {
			return wc_clean( $this->data[ $key ] );
		}
		if ( is_object( $this->data ) && isset( $this->data->{$key} ) ) {
			return wc_clean( $this->data->{$key} );
		}
	}

	/**
	 * Set Request Data
	 *
	 * @param array
	 */
	public function set_request_data ( array $data ) {
		if ( ! empty( $this->data ) ) {
			$this->data = array_merge( (array) $this->data, $data );
		} else {
			$this->data = $data;
		}
	}
}
