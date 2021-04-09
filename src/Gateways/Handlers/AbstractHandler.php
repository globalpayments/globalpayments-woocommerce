<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use \WC_Order;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;

abstract class AbstractHandler implements HandlerInterface {
	/**
	 * Current request
	 *
	 * @var RequestInterface
	 */
	protected $request;

	/**
	 * Current response
	 *
	 * @var Transaction
	 */
	protected $response;

	/**
	 * Instantiates a new request
	 *
	 * @param RequestInterface $request
	 * @param Transaction $response
	 */
	public function __construct( RequestInterface $request, Transaction $response ) {
		$this->request  = $request;
		$this->response = $response;
	}

	/**
	 * Save post meta to order
	 *
	 * @param WC_Order $order
	 * @param array $meta
	 *
	 * @return
	 */
	protected function save_meta_to_order( WC_Order $order, array $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $order->get_id(), sprintf( '_globalpayments_%s', $key ), $value );
		}
	}
}
