<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;

interface HandlerInterface {
	/**
	 * Instantiates a new request
	 *
	 * @param RequestInterface $request
	 * @param Transaction $response
	 */
	public function __construct( RequestInterface $request, Transaction $response );

	/**
	 * Handles the response
	 *
	 * @return
	 */
	public function handle();
}
