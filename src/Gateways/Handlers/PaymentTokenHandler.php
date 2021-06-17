<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;

class PaymentTokenHandler extends AbstractHandler {
	public function handle() {
		if ( empty( $this->response->token ) ) {
		    return;
		}

		( new PaymentTokenData( $this->request ) )->save_new_token( $this->response->token, $this->response->cardBrandTransactionId );
	}
}
