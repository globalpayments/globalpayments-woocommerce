<?php
/**
 * WC GlobalPayments Admin Pay for Order Trait
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Trait PayOrderTrait
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits
 */
trait PayOrderTrait {
	/**
	 * @param $order
	 */
	public function pay_order_modal( $order ) {
		if ( $order->get_type() !== 'shop_order' || ! $order->has_status( array( 'pending', 'failed' ) ) ) {
			return;
		}

		// The HTML needed for the `Pay for Order` modal
		include_once( Plugin::get_path() . '/includes/admin/views/html-pay-order.php' );

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
			'globalpayments_admin_params',
			array(
				'_wpnonce'            => wp_create_nonce( 'woocommerce-globalpayments-pay' ),
				'gateway_id'          => $this->id,
				'payorder_url'        => WC()->api_request_url( 'globalpayments_pay_order' ),
				'payment_methods'     => $this->get_payment_methods( $order->get_customer_id() ),
				'payment_methods_url' => WC()->api_request_url( 'globalpayments_get_payment_methods' ),
			)
		);
		wp_enqueue_style(
			'globalpayments-admin',
			Plugin::get_url( '/assets/admin/css/globalpayments-admin.css' ),
			array(),
			WC()->version
		);
	}

	/**
	 * Endpoint for retrieving Customer payment methods.
	 */
	public function pay_order_modal_get_payment_methods() {
		$payment_methods = array();
		$nonce_value     = wc_get_var( $_REQUEST['_wpnonce'], '' ); // @codingStandardsIgnoreLine.
		if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-globalpayments-pay' ) ) {
			wp_send_json( $payment_methods );
		}
		$customer_id = absint( $_GET['customer_id'] );
		wp_send_json( $this->get_payment_methods( $customer_id ) );
	}

	/**
	 * Retrieve Customer payment methods.
	 *
	 * @param int $customer_id
	 *
	 * @return array
	 */
	private function get_payment_methods( int $customer_id ) {
		$payment_methods = array();
		if ( empty( $customer_id ) ) {
			return $payment_methods;
		}
		$tokens = \WC_Payment_Tokens::get_customer_tokens( $customer_id, $this->id );
		foreach ( $tokens as $token ) {
			$payment_methods[] = array(
				'id'           => $token->get_id(),
				'display_name' => $token->get_display_name(),
				'is_default'   => $token->is_default(),
			);
		}

		return $payment_methods;
	}

	/**
	 * Endpoint for processing the payment in Admin modal.
	 */
	public function pay_order_modal_process_payment() {
		try {
			// Validate modal request
			if ( ! isset( $_POST['woocommerce_globalpayments_pay'] ) ) {
				throw new \Exception( __( 'Invalid payment request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			wc_nocache_headers();

			$nonce_value = wc_get_var( $_REQUEST['woocommerce-globalpayments-pay-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.
			if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-globalpayments-pay' ) ) {
				throw new \Exception( __( 'Invalid payment request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			$order_key = wc_get_post_data_by_key( 'order_key' );
			$order_id  = (int) wc_get_post_data_by_key( 'order_id' );
			$order     = wc_get_order( $order_id );
			if ( $order_id !== $order->get_id() || ! hash_equals( $order->get_order_key(), $order_key ) ) {
				throw new \Exception( __( 'Invalid payment request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			// Validate
			if ( empty( abs( $order->get_total() ) ) ) {
				throw new \Exception( __( 'Invalid amount. Order total must be greater than zero.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
			if ( ! empty( $_POST['transaction_id'] ) && empty( $_POST['allow_order'] ) ) {
				throw new \Exception( __( 'This order has a transaction ID associated with it already. Please click the checkbox to proceed with payment.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			// Update payment method.
			$order->set_payment_method( $this->id );
			$order->save();

			// Process Payment
			$result = $this->process_payment( $order_id );
			if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
				wp_send_json( [
					'success' => true,
				] );
			} else {
				throw new Exception( __( 'Something went wrong while processing the payment.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json( [
				'error'   => true,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Secure payment fields styles for Admin modal.
	 *
	 * @param string $secure_payment_fields_styles CSS styles.
	 *
	 * @return false|string
	 */
	public function pay_order_modal_secure_payment_fields_styles( $secure_payment_fields_styles ) {
		$secure_payment_fields_styles = json_decode( $secure_payment_fields_styles, true );

		$secure_payment_fields_styles['#secure-payment-field-wrapper'] = array(
			'justify-content' => 'flex-end',
		);
		$secure_payment_fields_styles['#secure-payment-field'] = array(
			'background-color' => '#fff',
			'border'           => '1px solid #ccc',
			'border-radius'    => '4px',
			'display'          => 'block',
			'font-size'        => '13px',
			'font-weight'      => '400',
			'height'           => '35px',
			'padding'          => '6px 12px',
			'font-family'      => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important',
		);

		$secure_payment_fields_styles['#secure-payment-field[type=button]:focus'] = array(
			'color'        => '#fff',
			'background'   => '#135e96',
			'border-color' => '#135e96',
		);
		$secure_payment_fields_styles['#secure-payment-field[type=button]:hover'] = array(
			'color'        => '#fff',
			'background'   => '#135e96',
			'border-color' => '#135e96',
		);
		$secure_payment_fields_styles['button#secure-payment-field.submit'] = array(
			'background'      => '#2271b1',
			'border-color'    => '#2271b1',
			'border-radius'   => '3px',
			'color'           => '#fff',
			'cursor'          => 'pointer',
			'display'         => 'inline-block',
			'flex'            => '0',
			'line-height'     => '23px',
			'margin-bottom'   => '0',
			'min-height'      => '32px',
			'padding'         => '0px 12px 0px 12px',
			'text-align'      => 'center',
			'text-decoration' => 'none',
			'text-shadow'     => 'none',
			'white-space'     => 'nowrap',
		);

		return json_encode( $secure_payment_fields_styles );
	}
}
