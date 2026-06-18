<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;

class PaymentTokenHandler extends AbstractHandler {
	public function handle() {
		if ( empty( $this->response->token ) ) {
		    return;
		}

		// Check if using a stored payment token (existing card)
		$gateway = $this->request->get_request_data( 'payment_method' );
		$stored_token_id = $this->request->get_request_data( sprintf( 'wc-%s-payment-token', $gateway ) );
		
		// If using a stored token (not 'new'), don't try to save a new token
		if ( ! empty( $stored_token_id ) && 'new' !== $stored_token_id ) {
			return;
		}

		( new PaymentTokenData( $this->request ) )->save_new_token( $this->response->token, $this->response->cardBrandTransactionId );
	}
}
