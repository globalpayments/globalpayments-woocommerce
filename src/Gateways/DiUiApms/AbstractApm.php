<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\DiUiApms;

use Automattic\WooCommerce\Internal\DependencyManagement\ContainerException;
use Exception;
use Automattic\WooCommerce\Caching\CacheException;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use WC_Order;
use WC_Order_Refund;
use WP_Exception;

defined('ABSPATH') || exit;

/**
 * 
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\DiUiApms
 */
class AbstractApm{

    /**
     * Handle payment status callback from payment gateway
     * 
     * @return void 
     * @throws WP_Exception 
     * @throws ContainerException 
     * @throws Exception 
     * @throws CacheException 
     */
	public static function handle_gpapi_apm_status_notification() : void
	{
		// Get request data (could be GET or POST)
		$request_data = array_merge( $_GET, $_POST );

		// Parse JSON body if present
		$raw_input = file_get_contents( 'php://input' );
		if ( !empty( $raw_input ) ) {
			$json_data = json_decode( $raw_input, true );
			if ( $json_data ) {
				$request_data = array_merge( $request_data, $json_data );
			}
		}

		// Extract transaction details
		$transaction_id = self::extract_transaction_id( $request_data );
		$payment_status = self::extract_payment_status( $request_data );

		if ( !empty( $request_data['reference'] ) ) {
			$order_id = str_replace( "WooCommerce_Order_", "", $request_data['reference'] );

			// Find the order
			$order = wc_get_order( $order_id );

			// If status notification transaction id and order transaction id are
			// the same then update the order status
			if ( $order->get_transaction_id() === $transaction_id )
				self::update_order_status_from_notification( $order, $payment_status, $transaction_id, $request_data );
		}
	}

    /**
     * Extract transaction ID from callback data
     * 
     * @param array $data 
     * @return string|null 
     * @throws WP_Exception 
     */
	protected static function extract_transaction_id( array $data ) : ?string
	{
		// Common field names for transaction ID
		$possible_fields = ['id', 'reference', 'notification'];

		foreach ( $possible_fields as $field ) {
			if ( isset( $data[$field] ) && !empty( $data[$field] ) ) {
				return sanitize_text_field( $data[$field] );
			}
		}

		return null;
	}

    /**
     * Extract payment status from callback data
     * 
     * @param array $data 
     * @return string 
     * @throws WP_Exception 
     */
	protected static function extract_payment_status( array $data ) : string
	{
		// Common field names for status
		$possible_fields = ['status'];

		foreach ( $possible_fields as $field ) {
			if ( isset( $data[$field] ) && !empty( $data[$field] ) ) {
				return sanitize_text_field( $data[$field] );
			}
		}

		return 'UNKNOWN';
	}

    /**
     * Update order status based on payment notification
     * 
     * @param bool|WC_Order|WC_Order_Refund $order 
     * @param string $payment_status 
     * @param string $transaction_id 
     * @param array $callback_data 
     * @return void 
     */
	protected static function update_order_status_from_notification(
		bool|WC_Order|WC_Order_Refund $order,
		string $payment_status,
		string $transaction_id,
		array $callback_data
	) : void
    {
		$status_upper = strtoupper( $payment_status );

		// Create order note with callback details
		$callback_summary = "Status: $payment_status, Transaction ID: $transaction_id";
		if ( isset( $callback_data['amount'] ) ) {
			$callback_summary .= ", Amount: " . $callback_data['amount'];
		}

		switch ( $status_upper ) {
			case 'CAPTURED':
				if ( !in_array( $order->get_status(), array( 'processing', 'completed' ) ) ) {
					$note_text = sprintf(
						'Payment completed. %s',
						$callback_summary
					);
					$order->add_order_note( $note_text );
					$order->payment_complete( $transaction_id );

					// Add metadata to track callback processing
					$order->update_meta_data( '_gpapi_apm_callback_processed', date( 'Y-m-d H:i:s' ) );
					$order->update_meta_data( '_gpapi_apm_payment_status', $payment_status );
					$order->save();
				}
				break;
			case 'DECLINED':
				if ( in_array( $order->get_status(), array( 'on-hold', 'pending' ) ) ) {
					$note_text = sprintf(
						'Payment failed/declined via status notification. %s',
						$callback_summary
					);
					$order->update_status( 'cancelled', $note_text );

					// Add metadata to track callback processing
					$order->update_meta_data( '_gpapi_apm_callback_processed', date( 'Y-m-d H:i:s' ) );
					$order->update_meta_data( '_gpapi_apm_payment_status', $payment_status );
					$order->save();
				}
				break;
		}
	}

    /**
     * Handle payment redirect back to shop
     * 
     * @return void 
     */
	public static function handle_gpapi_apm_success_redirect() : void
	{
		$gateway = new GpApiGateway();

		if ( $_REQUEST["status"] === "DECLINED" ) {			
			wc_get_order(
				str_replace( "WooCommerce_Order_", "", $_REQUEST["reference"] )
			)->add_order_note( sprintf( 'Payment declined or was cancelled by customer.' ) );

			wp_safe_redirect( wc_get_checkout_url() );
		} elseif( $_REQUEST["status"] === "CAPTURED" ) {
			self::handle_gpapi_apm_status_notification();

			WC()->cart->empty_cart();

			wp_safe_redirect(
				$gateway->get_return_url(
					wc_get_order(
						str_replace( "WooCommerce_Order_", "", $_REQUEST["reference"] )
					)
				)
			);
		} else {
			WC()->cart->empty_cart();
			
			wp_safe_redirect( $gateway->get_return_url() );
		}
	}
}
