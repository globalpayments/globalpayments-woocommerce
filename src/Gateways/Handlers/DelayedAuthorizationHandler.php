<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

class DelayedAuthorizationHandler extends AbstractHandler {
	public function handle() {
		if ( null === $this->request->order ) {
			return;
		}

		if ( AbstractGateway::TXN_TYPE_VERIFY !== $this->request->get_transaction_type() ) {
			return;
		}

		$meta = array(
			'amount'         => $this->request->order->get_total(),
			'currency'       => $this->request->order->get_currency(),
			'invoice_number' => $this->request->order->get_id(),
			// descriptor
			// 'cardholder'
		);

		$this->save_meta_to_order( $this->request->order, $meta );
	}
}
