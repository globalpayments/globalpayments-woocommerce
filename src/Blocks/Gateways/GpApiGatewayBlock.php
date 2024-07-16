<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;

class GpApiGatewayBlock extends AbstractGatewayBlock {
	public function initialize() {
		$this->name    = GpApiGateway::GATEWAY_ID;

		parent::initialize();
	}
}
