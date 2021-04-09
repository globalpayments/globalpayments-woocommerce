<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Clients;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;

interface ClientInterface {
	/**
	 * Sets request object for gateway request. Triggers creation of SDK
	 * compatible objects from request data.
	 *
	 * @param RequestInterface $request
	 *
	 * @return ClientInterface
	 */
	public function set_request( RequestInterface $request );

	/**
	 * Executes desired transaction with gathered data
	 *
	 * @return Transaction
	 */
	public function execute();
}
