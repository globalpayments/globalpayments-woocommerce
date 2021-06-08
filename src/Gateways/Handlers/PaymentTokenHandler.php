<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;

class PaymentTokenHandler extends AbstractHandler {
	public function handle() {
		if ( empty( $this->response->token ) ) {
			wc_add_notice( __( 'Payment method token not received from gateway.', 'globalpayments-gateway-provider-for-woocommerce' ), 'error' );
			throw new \Exception( __( 'Payment method token not received from gateway.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		( new PaymentTokenData( $this->request ) )->save_new_token( $this->response->token, $this->response->cardBrandTransactionId );
	}
}
