<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

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
	}

	/**
	 * Retrieve Card Holder Name either from Hosted Fields or Billing Address.
	 *
	 * @param $customer_name
	 *
	 * @return mixed
	 */
	private function get_card_holder_name( $customer_name ) {
		if ( is_array( $this->data ) && isset( $this->data[$this->gateway_id] ) ) {
			$data = json_decode( stripslashes( $this->data[$this->gateway_id]['token_response'] ) );
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
		);
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

		if ( ! isset( $this->data[ $key ] ) ) {
			return null;
		}

		return wc_clean( $this->data[ $key ] );
	}

	/**
	 * Set Request Data
	 *
	 * @param array
	 */
	public function set_request_data ( array $data ) {
		if ( ! empty( $this->data ) ) {
			$this->data = array_merge( $this->data, $data );
		} else {
			$this->data = $data;
		}
	}
}
