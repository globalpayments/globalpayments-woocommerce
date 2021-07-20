<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;

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
		$this->add_order_note();

		if ( AbstractGateway::TXN_TYPE_VERIFY !== $txn_type ) {
			$this->request->order->payment_complete( $this->response->transactionId );
			return;
		}

		$this->request->order->set_transaction_id( $this->response->transactionId );
		$this->request->order->save();
	}

	private function add_order_note() {
		$config = $this->request->get_default_args()[ RequestArg::SERVICES_CONFIG ];
		if ( GatewayProvider::GP_API !== $config['gatewayProvider'] ) {
			return;
		}
		if ( Environment::PRODUCTION === $config['environment'] ) {
			return;
		}
		$this->request->order->add_order_note( __( 'This order was placed in [SANDBOX_MODE].' ) );
	}
}
