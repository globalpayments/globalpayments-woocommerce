<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HppApms\AbstractHppApm;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\HppResponseParser;
use GlobalPayments\WooCommercePaymentGatewayProvider\Services\InstallmentsService;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\HPPAllowedPaymentMethods;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * HPP trait
 *
 * Provides HPP-specific functionality to the GpAPI HPP
 * Handles all the return logic for HPP and signature validation
 *
 * @since 1.14.9
 */
trait HppTrait {

	/**
	 *  Initialize HPP functionality
	 *
	 * @return void
	 */
	public function init_hpp(): void {
		if ( $this->is_hpp_mode() ) {
			$this->add_hpp_hooks();
			$this->init_installments_hooks();
		}
	}

	/**
	 * Add HPP-specific hooks
	 */
	protected function add_hpp_hooks(): void {
		add_action( 'woocommerce_api_globalpayments_hpp_return', array( $this, 'process_hpp_return' ) );
		add_action( 'woocommerce_api_globalpayments_hpp_status', array( $this, 'process_hpp_status' ) );
		add_action( 'woocommerce_api_globalpayments_hpp_cancel', array( $this, 'process_hpp_cancel' ) );
		add_action( 'woocommerce_api_globalpayments_hpp_final', array( $this, 'process_hpp_final' ) );

		// Guard against a duplicate order when the shopper paid but never reached the
		// order-received page (so their cart was never emptied) and returns to checkout.
		add_action( 'template_redirect', array( $this, 'maybe_prevent_duplicate_hpp_order' ) );

		// Classic checkout hooks - Make fields required and adds additional validation
		add_filter( 'woocommerce_checkout_fields', array( $this, 'hpp_three_d_secure_required_fields' ), 1000, 1 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_hpp_required_fields' ), 1000, 2 );
		if( $this->enable_eraty_hpp ){
			// Disable refund UI for non-refundable ERATY payments
			add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_eraty_refund_tooltip' ) );
		}
		
	}

	/**
	 * Process HPP payment, called from process_payment from GpApiGateway
	 *
	 * @param int $order_id
	 * @return array Contains HPP URL or error message on failure
	 */
	public function process_hpp_payment( int $order_id ): array {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {

			return array(
				'result'  => 'failure',
				'message' => __( 'Invalid order', 'globalpayments-gateway-provider-for-woocommerce' ),
			);
		}

		// Validate nonce for security
		if ( ! $this->validate_hpp_nonce() ) {
			if ( $this->debug ) {
				$logger->error( 'HPP Payment Processing: Nonce validation failed', $context );
			}

			wc_add_notice(
				__( 'Security check failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'error'
			);
			return array( 'result' => 'failure' );
		}
		if ( 'YES' === strtoupper( $this->enable_three_d_secure ) ) {
			// Ensure phone number is provided for 3DS
			$billing_phone  = $order->get_billing_phone();

			if ( empty( trim( $billing_phone ) ) ) {
				if ( $this->debug ) {
					$logger->error( 'HPP Payment Processing: Missing phone number for 3DS', $context );
				}

				wc_add_notice(
					__(
						'Phone number is required for 3D Secure transactions. Please provide a phone number for Billing and try again.',
						'globalpayments-gateway-provider-for-woocommerce'
					),
					'error'
				);

				return array( 'result' => 'failure' );
			}
		}
		try {
			// Use standard request-response pattern like other payment methods
			$request          = $this->prepare_request( AbstractGateway::TXN_TYPE_CREATE_HPP, $order );
			$gateway_response = $this->submit_hpp_request( $request );

			// Extract HPP URL from response
			$hpp_url = HppResponseParser::extract_hpp_url_from_response( $gateway_response );

			if ( empty( $hpp_url ) ) {
				throw new \Exception( 'Failed to create HPP URL from gateway response' );
			}

			// Add order note
			$note_text = sprintf(
				__( 'HPP payment initiated for %1$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
				wc_price( $order->get_total() ),
				$gateway_response->transactionId ?? 'N/A'
			);
			$order->add_order_note( $note_text );

			if ( ! empty( $gateway_response->transactionId ) ) {
				$order->set_transaction_id( $gateway_response->transactionId );
				$order->save();
			}

			// The HPP posts its result back cross-site, so that callback has no shopper session
			// and cannot empty the cart. Record the order in the shopper's own session here (this
			// runs in their session) so the cart can be cleared on their next front-end load.
			if ( WC()->session ) {
				WC()->session->set( 'gp_hpp_awaiting_order', $order_id );
				// TEMP DIAGNOSTIC - remove once cart-clearing is confirmed working.
				$logger->info( sprintf( 'HPP set awaiting-order session key: order #%d', $order_id ), $context );
			}

			return array(
				'result'   => 'success',
				'redirect' => $hpp_url,
			);

		} catch ( \Exception $e ) {
			if ( $this->debug ) {
				$logger->error( 'HPP Payment Processing: Exception occurred', $context );
			}

			wc_add_notice(
				__( 'Unable to process payment. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'error'
			);
			return array( 'result' => 'failure' );
		}
	}

	/**
	 * Get provider endpoints for HPP
	 *
	 * @return array
	 */
	public function get_hpp_provider_endpoints(): array {
		return array(
			'returnUrl' => WC()->api_request_url( 'globalpayments_hpp_return', true ),
			'statusUrl' => WC()->api_request_url( 'globalpayments_hpp_status', true ),
			'cancelUrl' => wc_get_checkout_url() . '?cancelled=1',
		);
	}

	/**
	 * Submit HPP request
	 *
	 * @param mixed $request
	 * @return Transaction Containing an payByLinkResponse property with HPP URL
	 */
	protected function submit_hpp_request( $request ): Transaction {
		$request->set_request_data(
			array(
				'globalpayments_hpp' => $this->get_hpp_provider_endpoints(),
			)
		);

		$gateway_response = $this->client->submit_request( $request );
		$this->handle_response( $request, $gateway_response );

		return $gateway_response;
	}


	/**
	 * Validate HPP nonce from request
	 *
	 * Skips validation when a new customer account was just created during checkout,
	 * because WordPress nonces are tied to user session - the nonce generated for a guest
	 * becomes invalid after the user is logged in.
	 *
	 * @return bool True if valid, false otherwise
	 */
	protected function validate_hpp_nonce(): bool {
		// Skip nonce validation if a new customer was just created during checkout.
		// WooCommerce's checkout nonce is still validated before process_payment().
		if ( did_action( 'woocommerce_created_customer' ) > 0 ) {
			return true;
		}

		$nonce = $this->get_hpp_nonce_from_request();

		if ( empty( $nonce ) ) {
			return false;
		}

		return wp_verify_nonce( $nonce, 'gp_hpp_payment' );
	}

	/**
	 * Extract nonce from POST data
	 *
	 * @return string containing the nonce empty if not found
	 */
	protected function get_hpp_nonce_from_request(): string {
		// Classic Checkout
		if ( isset( $_POST['gp_hpp_nonce'] ) ) {
			return sanitize_text_field( $_POST['gp_hpp_nonce'] );
		}

		// Block checkout
		if ( isset( $_POST['payment_method_data']['gp_hpp_nonce'] ) ) {
			return sanitize_text_field( $_POST['payment_method_data']['gp_hpp_nonce'] );
		}

		return '';
	}

	/**
	 * Process HPP return callback
	 * wp_die called on signature validation failure
	 *
	 * @return void
	 */
	public function process_hpp_return(): void {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		// Get and validate the signature
		$signature = $this->obtainSignature();
		$raw_input = file_get_contents( 'php://input' );

		if ( ! $this->validate_hpp_return_signature( $raw_input, $signature ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP return signature validation failed', $context );
			}
			wp_die( __( 'Invalid signature', 'globalpayments-gateway-provider-for-woocommerce' ), 403 );
			return;
		}

		$input_data = json_decode( $raw_input, true );
		if ( ! is_array( $input_data ) || empty( $input_data ) ) {
			if ( $this->debug ) {
				$logger->error( 'Failed to parse HPP return data JSON', $context );
			}
			wp_die( __( 'Invalid data', 'globalpayments-gateway-provider-for-woocommerce' ), 400 );
			return;
		}

		// Finalize the order server-side from the validated return payload. The return
		// page's auto-submit to the final endpoint only runs if the shopper's browser
		// stays open, so relying on it leaves successful (and other) outcomes stranded
		// in 'pending' when the shopper closes the browser. Doing it here does not depend
		// on client-side JavaScript.
		$this->update_order_status_from_hpp_return( $input_data );

		// Render the return page
		$this->render_hpp_return_page( $signature, $raw_input );
	}

	/**
	 * Finalize the WooCommerce order from a validated HPP return payload.
	 *
	 * Runs server-side while rendering the return page, so the order is finalized even
	 * when the shopper closes the browser before the auto-submit form posts to the final
	 * endpoint. Delegates to the idempotent apply_hpp_result_to_order(), which guards
	 * against double-processing if the final callback later runs for the same order.
	 *
	 * @param array $gateway_data Decoded, signature-validated HPP return payload.
	 * @return void
	 */
	protected function update_order_status_from_hpp_return( array $gateway_data ): void {
		$order_id = absint( HppResponseParser::extract_order_id( $gateway_data ) );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->apply_hpp_result_to_order( $order, $gateway_data );
	}

	/**
	 * Process HPP status callback
	 * Handles status updates for all HPP payment methods
	 *
	 * @return void
	 */
	public function process_hpp_status(): void {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		// Get signature from header and raw input
		$signature = $this->obtainSignature();
		$raw_input = file_get_contents( 'php://input' );

		// Validate signature for status URL callback (uses same validation as return URL)
		if ( ! $this->validate_hpp_return_signature( $raw_input, $signature ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP Status: Invalid request signature', $context );
			}
			wp_die( 'Invalid signature', 403 );
			return;
		}

		// Delegate to AbstractHppApm for handling status notifications
		AbstractHppApm::handle_hpp_status_notification();
	}

	/**
	 * Process HPP cancel callback
	 * Not currently implemented
	 *
	 * @return void
	 */
	public function process_hpp_cancel(): void {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		wp_redirect( wc_get_checkout_url() . '?cancelled=1' );
		exit;
	}

	/**
	 * Process final HPP response before processing the order
	 *
	 * @return void
	 */
	public function process_hpp_final(): void {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		// Validate and sanitize POST data
		if ( ! isset( $_POST['X-GP-Signature'] ) || ! isset( $_POST['gateway_response'] ) ) {
			if ( $this->debug ) {
				$logger->error( 'Missing required POST data in HPP final', $context );
			}
			wp_die( __( 'Missing required data', 'globalpayments-gateway-provider-for-woocommerce' ), 400 );
		}

		$signature             = sanitize_text_field( wp_unslash( $_POST['X-GP-Signature'] ) );
		$gateway_response_json = wp_unslash( $_POST['gateway_response'] );

		// Clean the JSON data
		$gateway_response_json = $this->sanitize_hpp_json_input( $gateway_response_json );

		if ( ! $this->validate_hpp_return_signature( $gateway_response_json, $signature ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP final signature validation failed', $context );
			}
			wp_die( __( 'Invalid signature', 'globalpayments-gateway-provider-for-woocommerce' ), 403 );
		}

		$gateway_data = json_decode( $gateway_response_json, true );
		if ( ! is_array( $gateway_data ) || empty( $gateway_data ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP Callback Processing: Failed to parse gateway data', $context );
			}
			wp_die( __( 'Invalid response data', 'globalpayments-gateway-provider-for-woocommerce' ), 400 );
		}

		// Extract order and process the result
		$order_id = HppResponseParser::extract_order_id( $gateway_data );
		$order_id = absint( $order_id );

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			if ( $this->debug ) {
				$logger->error( 'HPP Callback Processing: Order not found', $context );
			}
			wp_die( __( 'Order not found', 'globalpayments-gateway-provider-for-woocommerce' ), 404 );
		}

		$redirect_url = $this->apply_hpp_result_to_order( $order, $gateway_data );

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Apply a signature-verified HPP gateway result to an order.
	 *
	 * Idempotent and safe to call from both the server-to-server return callback and the
	 * browser auto-submit final callback, so order completion no longer depends on
	 * client-side JavaScript running in the shopper's browser.
	 *
	 * @param WC_Order $order        Order located from the gateway response reference.
	 * @param array    $gateway_data Signature-verified gateway response data.
	 * @return string Redirect URL for the browser flow.
	 */
	protected function apply_hpp_result_to_order( WC_Order $order, array $gateway_data ): string {
		if ( $order->get_payment_method() !== $this->id ) {
			return wc_get_checkout_url();
		}

		// Guard against double-processing when both the return and final callbacks fire.
		$already_final = in_array( $order->get_status(), array( 'processing', 'completed' ), true );
		if ( HppResponseParser::is_successful_payment( $gateway_data ) ) {

		// Handle installments before payment_complete() to ensure data is available in emails.
			if ( InstallmentsService::has_installments( $gateway_data ) ) {
				$this->save_installment_data( $order, $gateway_data );
			}
	
			if ( !empty( $gateway_data['payment_method']['apm']['provider'] ) ) {
					if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
						$order->add_meta_data( '_globalpayments_apm_provider', $gateway_data['payment_method']['apm']['provider'] );
						$order->save();
					}else{
						update_post_meta( $order->get_id(), "_globalpayments_apm_provider", $gateway_data['payment_method']['apm']['provider'] );
					}
			}

			if ( ! $already_final ) {
				$transaction_id  = $gateway_data['id'] ?? '';
				$previous_status = $order->get_status();
				
				$order->payment_complete( $transaction_id );
				$order->add_order_note(
					sprintf(
						__( 'Payment completed via HPP. Transaction ID: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						$transaction_id
					)
				);

				// A late-but-valid payment can arrive after the unpaid-order timeout cancelled
				// the order; surface the recovery so staff can verify stock/fulfilment.
				if ( 'cancelled' === $previous_status ) {
					$order->add_order_note(
						sprintf(
							__( 'Order recovered from Cancelled by a verified HPP payment. Please review stock and fulfilment. Transaction ID: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
							$transaction_id
						)
					);
				}
			}

			// Empty the paid cart for the shopper's browser even when the server-to-server
			// status notification already finalized the order (which runs without a session
			// and so cannot clear the cart), so the same items can't be accidentally re-ordered.
			$this->empty_hpp_cart_for_order( $order );

			return $order->get_checkout_order_received_url();
		}

		if ( HppResponseParser::is_pending_payment( $gateway_data ) ) {
			if ( ! $already_final ) {
				$transaction_id = $gateway_data['id'] ?? '';
				$pending_note   = sprintf(
					__( 'Payment is pending. Transaction ID: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
					$transaction_id
				);
				if ( 'PENDING' === strtoupper( $order->get_status() ) ) {
					$order->add_order_note( $pending_note );
				} else {
					$order->update_status( 'pending', $pending_note );
				}
			}

			return $order->get_checkout_order_received_url();
		}

		if ( ! $already_final ) {
			$error_message = HppResponseParser::get_error_message( $gateway_data );
			$order->update_status( 'failed', $error_message );
			wc_add_notice( $error_message, 'error' );
		}

		return wc_get_checkout_url();
	}

	/**
	 * Empty the cart tied to a paid HPP order.
	 *
	 * Clears the current session cart when the paying browser is present, and removes the
	 * logged-in customer's persistent cart so a completed payment can't be re-ordered even
	 * when only the server-to-server notification (no shopper session) finishes the order.
	 *
	 * @param WC_Order $order Paid order.
	 * @return void
	 */
	protected function empty_hpp_cart_for_order( WC_Order $order ): void {
		if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			WC()->cart->empty_cart();
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'order_awaiting_payment', null );
			WC()->session->set( 'gp_hpp_awaiting_order', null );
		}

		$customer_id = $order->get_customer_id();
		if ( $customer_id ) {
			delete_user_meta( $customer_id, '_woocommerce_persistent_cart_' . get_current_blog_id() );
		}
	}

	/**
	 * Empty a paid HPP cart on the shopper's next front-end load, and stop a duplicate order.
	 *
	 * The HPP posts its result to returnUrl as a cross-site request, so the shopper's
	 * WooCommerce session and cart are not attached during the callback (SameSite cookies)
	 * and the cart cannot be emptied there. The order still finalizes because it is keyed off
	 * the signed order reference, so a shopper who closes the browser after paying is left with
	 * a paid order but a full cart. To recover, the order id is stored under our own session
	 * key when the shopper is redirected to the HPP; this runs on template_redirect - in the
	 * shopper's own session - so on their next front-end load that order is checked and, if it
	 * is paid, the cart is emptied. WooCommerce's order_awaiting_payment is unreliable for this
	 * gateway (it is not set for the order-pay flow), so it is only a fallback. On the checkout
	 * page they are also redirected to the existing order to prevent a second one.
	 *
	 * @return void
	 */
	public function maybe_prevent_duplicate_hpp_order(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! WC()->session || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$awaiting_order_id = absint( WC()->session->get( 'gp_hpp_awaiting_order' ) );
		if ( ! $awaiting_order_id ) {
			$awaiting_order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
		}

		if ( ! $awaiting_order_id ) {
			return;
		}

		// TEMP DIAGNOSTIC - remove once cart-clearing is confirmed working.
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		$order = wc_get_order( $awaiting_order_id );
		if ( ! $order instanceof WC_Order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		// TEMP DIAGNOSTIC
		$logger->info( sprintf( 'HPP dup-check: gateway=%s order #%d status=%s is_paid=%s', $this->id, $order->get_id(), $order->get_status(), $order->is_paid() ? 'yes' : 'no' ), $context );

		if ( ! $order->is_paid() ) {
			return;
		}

		$this->empty_hpp_cart_for_order( $order );

		// TEMP DIAGNOSTIC
		$logger->info( sprintf( 'HPP dup-check: emptied cart for paid order #%d', $order->get_id() ), $context );

		// Redirect only a shopper who returns to the checkout form; never on the order-received
		// endpoint (also a checkout page) to avoid a redirect loop, and not on other pages.
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			wc_add_notice(
				__( 'Your previous payment was received and your order is being processed. Your cart has been cleared to avoid placing a duplicate order.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'notice'
			);
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
	}

	/**
	 * Validate HPP return signature
	 *
	 * @param string $raw_input JSON input data
	 * @param string $signature recived signature
	 * @return bool True if valid, false otherwise
	 */
	protected function validate_hpp_return_signature( string $raw_input, string $signature ): bool {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		if ( empty( $raw_input ) || empty( $signature ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP: Empty signature or input data', $context );
			}
			return false;
		}

		// Clean escaped characters
		$clean_input = $this->sanitize_hpp_json_input( $raw_input );

		// Get the app key from admin settings
		$app_key = $this->get_credential_setting( 'app_key' );
		if ( empty( $app_key ) ) {
			if ( $this->debug ) {
				$logger->error( 'HPP: App key not found', $context );
			}
			return false;
		}

		// Generate expected signature
		$expected_signature = hash( 'sha512', $clean_input . $app_key );

		$signature_match = hash_equals( $expected_signature, $signature );

		return $signature_match;
	}

	/**
	 * Sanitize JSON input
	 *
	 * @param string $raw_input
	 * @return string cleaned JSON string
	 */
	protected function sanitize_hpp_json_input( string $raw_input ): string {
		// Check for escaped characters
		if ( false !== strpos( $raw_input, '\"' ) || false !== strpos( $raw_input, '\\' ) ) {

			$replacements = array(
				'\"'   => '"',
				'\\/'  => '/',
				'\\\\' => '\\',
			);

			$clean_input = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw_input );

			return $clean_input;
		}

		return $raw_input;
	}

	/**
	 * Render the HPP return page with auto-submit form
	 */
	protected function render_hpp_return_page( string $signature, string $input_data ): void {
		$logger  = wc_get_logger();
		$context = array( 'source' => 'globalpayments_hpp' );

		// Sanitize input data
		$signature  = sanitize_text_field( $signature );
		$input_data = $this->sanitize_hpp_json_input( $input_data );

		// Prepare view data for the template
		$final_url = WC()->api_request_url( 'globalpayments_hpp_final' );

		// Validate signature
		$signature_valid = $this->validate_hpp_return_signature( $input_data, $signature );

		// Parse JSON data to extract values for the view
		$parsed_input_data = json_decode( $input_data, true );

		// Calculate transaction outcome
		$transaction_outcome = 'FAILED'; // Default to failed
		if ( is_array( $parsed_input_data ) && ! empty( $parsed_input_data ) ) {
			$transaction_outcome = HppResponseParser::is_successful_payment( $parsed_input_data ) ? 'SUCCESS' : 'FAILED';
			$transaction_outcome = HppResponseParser::is_pending_payment( $parsed_input_data ) ? 'PENDING' : $transaction_outcome;
		}

		// Extract order ID
		$order_id = '';
		if ( isset( $parsed_input_data['link_data']['reference'] ) ) {
			$store_name = get_bloginfo( 'name' );
			$reference  = sanitize_text_field( $parsed_input_data['link_data']['reference'] );
			$order_id   = str_replace( $store_name . ' Order #', '', $reference );
			$order_id   = absint( $order_id );
		}

		// Extract transaction ID
		$transaction_id = sanitize_text_field( $parsed_input_data['id'] ?? '' );

		// Extract error message
		$error_message = ! empty( $parsed_input_data['payment_method']['message'] )
			? sanitize_text_field( $parsed_input_data['payment_method']['message'] )
			: __( 'Unfortunately, your payment could not be processed.', 'globalpayments-gateway-provider-for-woocommerce' );

		// Prepare template data
		$template_args = array(
			'gateway_response'    => $input_data,
			'signature_valid'     => $signature_valid,
			'gp_signature'        => $signature,
			'final_url'           => $final_url,
			'transaction_outcome' => $transaction_outcome,
			'order_id'            => $order_id,
			'transaction_id'      => $transaction_id,
			'error_message'       => $error_message,
		);

		// Load the template
		$template_file = Plugin::get_path() . '/includes/frontend/views/HPPReturn.php';
		if ( file_exists( $template_file ) ) {
			extract( $template_args );
			include $template_file;
		} else {
			// Fallback rendering if template not found
			$this->render_hpp_return_fallback( $signature, $input_data );
		}

		exit;
	}

	/**
	 * Fallback HTML for HPP return page
	 *
	 * @param string $signature received signature
	 * @param string $input_data_json JSON input data
	 * @return void
	 */
	protected function render_hpp_return_fallback( string $signature, string $input_data_json ): void {
		$final_url = WC()->api_request_url( 'globalpayments_hpp_final' );

		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?php esc_html_e( 'Processing Payment...', 'globalpayments-gateway-provider-for-woocommerce' ); ?></title>
		</head>
		<body>
			<h1><?php esc_html_e( 'Processing your payment...', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h1>
			<script>
				const form = document.createElement("form");
				form.method = "POST";
				form.action = "<?php echo esc_url( $final_url ); ?>";

				const signatureInput = document.createElement("input");
				signatureInput.type = "hidden";
				signatureInput.name = "X-GP-Signature";
				signatureInput.value = <?php echo wp_json_encode( $signature ); ?>;
				form.appendChild(signatureInput);

				const responseInput = document.createElement("input");
				responseInput.type = "hidden";
				responseInput.name = "gateway_response";
				responseInput.value = <?php echo wp_json_encode( $input_data_json ); ?>;
				form.appendChild(responseInput);

				document.body.appendChild(form);
				form.submit();
			</script>
		</body>
		</html>
		<?php
		exit;
	}


	/**
	 * Save installment data to order
	 *
	 * @param WC_Order $order
	 * @param array    $gateway_data
	 * @return void
	 */
	protected function save_installment_data( WC_Order $order, array $gateway_data ): void {
		$installment_data = $gateway_data['installment'] ?? array();

		if ( ! empty( $installment_data ) ) {
			$order->update_meta_data( '_globalpayments_installment_data', $installment_data );
			$order->update_meta_data( '_gp_has_installments', 'yes' );
			$order->add_order_note( wp_kses_post( InstallmentsService::format_installments_order_note( $installment_data['terms'] ) ) );
			$order->save();
		}
	}

	/**
	 * Initialize installments hooks for HPP
	 */
	protected function init_installments_hooks(): void {
		// Only initialize if payment interface is HPP
		if ( ! $this->is_hpp_mode() ) {
			return;
		}

		// Add installments info to order success page
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'display_installments_on_success_page' ), 2, 1 );

		// Add installments info to customer emails
		add_action( 'woocommerce_email_after_order_table', array( $this, 'add_installments_to_email' ), 20, 4 );
	}

	/**
	 * Display installments information on order success page
	 * Only for HPP payments
	 *
	 * @param $order_id
	 * @return void
	 *
	 * @param int $order_id
	 */
	public function display_installments_on_success_page( int $order_id ): void {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Additional check to ensure this is HPP mode
		if ( ! $this->is_hpp_mode() ) {
			return;
		}

		if ( InstallmentsService::order_has_installments( $order ) ) {
			// Render installments information
			echo InstallmentsService::render_installments_info( $order );
		}
	}

	/**
	 * Add installments information to customer emails (HPP only)
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 * @param WC_Email $email
	 */
	public function add_installments_to_email(
		\WC_Order $order,
		bool $sent_to_admin,
		bool $plain_text,
		\WC_Email $email
	): void {
		// Only show for customer emails, not admin emails
		if ( $sent_to_admin ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		if ( ! $this->is_hpp_mode() ) {
			return;
		}

		// Check order has been paid
		if ( ! $order->is_paid() ) {
			return;
		}

		// Only show for order emails where payment was successful
		$allowed_email_ids = array(
			'customer_processing_order',
			'customer_completed_order',
		);

		if ( ! in_array( $email->id, $allowed_email_ids ) ) {
			return;
		}

		// Check if order has installments data returned from external gateway
		if ( InstallmentsService::order_has_installments( $order ) ) {

			if ( $plain_text ) {
				InstallmentsService::render_installments_info_plaintext( $order );
			} else {
				echo InstallmentsService::render_installments_email_info( $order );
			}
		}
	}

	/**
	 * Sets the phone number fields to required when 3ds is enabled
	 *
	 * @param array $fields
	 * @return array
	 */
	public function hpp_three_d_secure_required_fields( array $fields ): array {
		// Only apply on classic checkout and HPP mode
		if ( ! $this->is_hpp_mode() ) {
			return $fields;
		}
		// Make billing phone required if 3DS is enabled
		if ( wc_string_to_bool( $this->enable_three_d_secure ) ) {
			if ( isset( $fields['billing']['billing_phone'] ) ) {
				$fields['billing']['billing_phone']['required'] = 1;
				// For WooCommerce validation
				$fields['billing']['billing_phone']['validate'] = 1;

			}
			if ( isset( $fields['shipping']['shipping_phone'] ) ) {
				$fields['shipping']['shipping_phone']['required'] = 1;
				// For WooCommerce validation
				$fields['shipping']['shipping_phone']['validate'] = 1;

			}
		}

		return $fields;
	}

	/**
	 * Further validation for required fields when payment_interface is HPP
	 *
	 * @param array     $data
	 * @param \WP_Error $errors
	 * @return void
	 */
	public function validate_hpp_required_fields( array $data, \WP_Error $errors ): void {
		if ( ! $this->is_hpp_mode() ) {
			return;
		}

		// Validate billing phone for 3DS
		if ( 'YES' === strtoupper( $this->enable_three_d_secure ) ) {
			if ( empty( trim( $data['billing_phone'] ?? '' ) ) ) {
				$errors->add(
					'validation',
					__(
						'Billing phone number is required for 3D Secure transactions. Please provide a valid phone number',
						'globalpayments-gateway-provider-for-woocommerce'
					)
				);
			}
		}
	}

	/**
	 * Gets the X-GP-Signature from the request headers, required for validation. 
	 * @return string
	 */
	private function obtainSignature(): string {
		$signature = '';
		if ( ! empty( $_REQUEST['X-GP-Signature'] ) ) {
			$signature = (string) $_REQUEST['X-GP-Signature'];
		} else {
			$headers   = array_change_key_case( Utils::get_all_headers() );
			$signature = ( ! empty( $headers['x-gp-signature'] ) ) ? $headers['x-gp-signature'] : '';

			// Final attempt to get the signature
			if ( '' === $signature && isset( $_SERVER['HTTP_X_GP_SIGNATURE'] ) &&
			! empty( $_SERVER['HTTP_X_GP_SIGNATURE'] ) ) {
				$signature = (string) $_SERVER['HTTP_X_GP_SIGNATURE'];
			}
		}
		return $signature;
	}

	/**
     * Determines if the shop location is either UK or Canada
     * @return bool
     */
	public function check_hpp_installments_eligibility() :bool
	{
		return InstallmentsService::hpp_installments_eligible();
	}

	/**
	 * Adds a tooltip to the refund button for eRaty orders
	 *
	 * @param WC_Order $order
	 */
	public function add_eraty_refund_tooltip( WC_Order $order ): void {
		if ( ! $this->is_eraty_order( $order ) ) {
			return;
		}

		$button_title =  __( "Eraty HPP payments cannot be refunded via the WooCommerce admin.", "globalpayments-gateway-provider-for-woocommerce" ) ;

		?>
		<script>
			( function() {
				var refundButton = document.querySelector( 'button.refund-items' );
				if ( refundButton ) {
					refundButton.disabled = true;
					refundButton.title = <?php echo wp_json_encode( $button_title ); ?>;
				}
			} )();
		</script>
		<?php
	}

	/**
	 * Confirms the order was processed by this gateway before checking for APM provider.
	 * 
	 * @param WC_Order $order
	 * @return bool True if the order was processed by this gateway and is an eRaty order
	 */
	protected function is_eraty_order( WC_Order $order ): bool {
		if ( empty( $order ) || $order->get_payment_method() !== $this->id ) {
			return false;
		}
		if( ! defined( HPPAllowedPaymentMethods::class . "::ERATY" ) ){
			return false;
		}
		$apm_provider = false;
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$apm_provider = $order->get_meta( '_globalpayments_apm_provider' );
		} else {
			$apm_provider = get_post_meta( $order->get_id(), '_globalpayments_apm_provider', true );
		}

		return ! empty( $apm_provider ) && HPPAllowedPaymentMethods::ERATY === strtoupper( $apm_provider );
	}
}
