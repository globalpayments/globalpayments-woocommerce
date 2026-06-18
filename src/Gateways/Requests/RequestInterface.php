<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use WC_Order;

interface RequestInterface {
	/**
	 * Instantiates a new request
	 *
	 * @param string $gateway_id
	 * @param WC_Order $order
	 * @param array $config
	 */
	public function __construct( $gateway_id, WC_Order $order = null, array $config = array() );

	/**
	 * Gets transaction type for the request
	 *
	 * @return string
	 */
	public function get_transaction_type();

	/**
	 * Gets request specific args
	 *
	 * @return array
	 */
	public function get_args();

	/**
	 * Gets default request args
	 *
	 * @return array
	 */
	public function get_default_args();

	/**
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function get_request_data( $key = null );

	/**
	 *
	 * @param array $data
	 * @return mixed
	 */
	public function set_request_data( array $data );
}
