<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Handlers;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking\OpenBanking;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm\Paypal;

class PaymentActionHandler extends AbstractHandler {
	protected $accepted_transaction_types = array(
		AbstractGateway::TXN_TYPE_AUTHORIZE,
		AbstractGateway::TXN_TYPE_SALE,
		AbstractGateway::TXN_TYPE_VERIFY,
		AbstractGateway::TXN_TYPE_DW_AUTHORIZATION,
		AbstractGateway::TXN_TYPE_OB_AUTHORIZATION,
		AbstractGateway::TXN_TYPE_PAYPAL_INITIATE,
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
					Klarna::PAYMENT_METHOD_ID,
					OpenBanking::PAYMENT_METHOD_ID,
					Paypal::PAYMENT_METHOD_ID,
				)
			)
		) {
			$this->request->order->payment_complete( $this->response->transactionId );

			if (
				AbstractGateway::TXN_TYPE_SALE === $txn_type
				&& ! empty( $this->response->installment )
			) {
				$installment_data = json_decode( wp_json_encode( $this->response->installment ), true );
				if ( ! empty( $installment_data ) ) {
					$this->request->order->update_meta_data( '_globalpayments_installment_data', $installment_data );
					$this->request->order->update_meta_data( '_gp_has_installments', 'yes' );
				}

				$installment_count = $installment_data['count'] ?? $installment_data['terms']['count'] ?? null;
				$auth_code = $this->response->transactionReference->authCode ?? null;

				$note_lines = array(
					sprintf(
						__( 'Order id: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						 $installment_data['id'] ?? null
					),
					sprintf(
						__( 'Response: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						__( $this->response->responseCode, 'globalpayments-gateway-provider-for-woocommerce' )
					),
					sprintf(
						__( 'Auth code: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						$auth_code ? $auth_code : __( 'N/A', 'globalpayments-gateway-provider-for-woocommerce' )
					),
					sprintf(
						__(
							'Installments number: %s installments',
							'globalpayments-gateway-provider-for-woocommerce'
						),
						$installment_count
							? $installment_count
							: __( 'N/A', 'globalpayments-gateway-provider-for-woocommerce' )
					),
				);

				$this->request->order->add_order_note( implode( "\n", $note_lines ) );
			}

			return;
		}

		$this->request->order->set_transaction_id( $this->response->transactionId );
		$this->request->order->save();
	}
}
