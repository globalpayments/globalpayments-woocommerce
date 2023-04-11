<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;

class PaymentActionHandler extends AbstractHandler {
	protected $accepted_transaction_types = array(
		AbstractGateway::TXN_TYPE_AUTHORIZE,
		AbstractGateway::TXN_TYPE_SALE,
		AbstractGateway::TXN_TYPE_VERIFY,
	);

	public function handle() {
		if ( null === $this->request->order ) {
			return;
		}

		$txn_type = $this->request->get_transaction_type();

		if ( ! in_array( $txn_type, $this->accepted_transaction_types, true ) ) {
			return;
		}

		$this->save_meta_to_order( $this->request->order, array( 'payment_action' => $txn_type ) );

		if (
			AbstractGateway::TXN_TYPE_VERIFY !== $txn_type
			&& ! in_array(
				$this->request->order->get_payment_method(),
				array(
					Affirm::PAYMENT_METHOD_ID,
					Clearpay::PAYMENT_METHOD_ID,
					Klarna::PAYMENT_METHOD_ID
				) )
		) {
			$this->request->order->payment_complete( $this->response->transactionId );

			return;
		}

		$this->request->order->set_transaction_id( $this->response->transactionId );
		$this->request->order->save();
	}
}
