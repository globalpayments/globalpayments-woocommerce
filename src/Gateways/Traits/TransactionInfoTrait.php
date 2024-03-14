<?php
/**
 * WC GlobalPayments Admin View Transaction Status Trait
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

use GlobalPayments\Api\Entities\Enums\PaymentMethodName;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

trait TransactionInfoTrait {
	/**
	 * @param $order
	 */
	public function transaction_info_modal( $order ) {
		if ( $order->get_type() !== 'shop_order' || ! $order->has_status( 'pending' ) || $this->id !== $order->get_payment_method()
		) {
			return;
		}

		// The HTML needed for the `View Transaction Status` modal
		include_once( Plugin::get_path() . '/includes/admin/views/html-transaction-info.php' );

		$this->tokenization_script();

		wp_enqueue_script(
			'globalpayments-modal',
			Plugin::get_url( '/assets/admin/js/globalpayments-modal.js' ),
			array(
				'jquery',
				'wc-backbone-modal',
				'jquery-blockui'
			),
			WC()->version,
			true
		);

		wp_enqueue_script(
			'globalpayments-admin',
			Plugin::get_url( '/assets/admin/js/globalpayments-admin.js' ),
			array(
				'jquery',
				'jquery-blockui',
				'globalpayments-modal'
			),
			WC()->version,
			true
		);

		wp_localize_script(
			'globalpayments-admin',
			'globalpayments_admin_txn_params',
			array(
				'_wpnonce'            => wp_create_nonce( 'woocommerce-globalpayments-view-transaction-info' ),
				'gateway_id'           => $this->id,
				'transaction_id'       => $order->get_transaction_id(),
				'transaction_info_url' => WC()->api_request_url( 'globalpayments_get_transaction_info' ),
			)
		);

		wp_enqueue_style(
			'globalpayments-admin',
			Plugin::get_url( '/assets/admin/css/globalpayments-admin.css' ),
			array(),
			WC()->version
		);
	}

	public function get_transaction_info() {
		try {
			$nonce_value = wc_get_var( $_REQUEST['woocommerce-globalpayments-view-transaction-info-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.
			if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-globalpayments-view-transaction-info' ) ) {
				throw new \Exception( __( 'Invalid view transaction request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			$transactionId = wc_get_var( $_REQUEST['transactionId'] );
			if ( ! $transactionId ) {
				throw new \Exception( __( 'Invalid transaction id.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			$response = $this->gateway->get_transaction_details_by_txn_id( $transactionId );

			$modalData = array (
				'transaction_id'     => $response->transactionId,
				'transaction_status' => $response->transactionStatus,
				'transaction_type'   => $response->transactionType,
				'amount'             => wc_format_decimal( $response->amount, 2 ),
				'currency'           => $response->currency,
				'payment_type'       => $response->paymentType ?? null,
			);

			if ( ! empty( $response->bnplResponse ) ) {
				$modalData['provider'] = PaymentMethodName::BNPL;
				$modalData['provider_type'] = $response->bnplResponse->providerName;
			} elseif ( ! empty( $response->alternativePaymentResponse ) ) {
				$modalData['provider'] = PaymentMethodName::APM;
				$modalData['provider_type'] = $response->alternativePaymentResponse->providerName;
			} elseif ( ! empty( $response->bankPaymentResponse ) ) {
				$modalData['provider'] = PaymentMethodName::BANK_PAYMENT;
				$modalData['provider_type'] = $response->bankPaymentResponse->type;
			}

			wp_send_json( $modalData );
		} catch ( \Exception $e ) {
			wp_send_json( [
				'error'   => true,
				'message' => $e->getMessage(),
			] );
		}
	}
}
