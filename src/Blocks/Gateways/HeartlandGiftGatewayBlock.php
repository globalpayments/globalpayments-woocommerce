<?php
/**
 * HeartlandGiftGatewayBlock - Handles gift card integration for WooCommerce Block checkout.
 *
 * This class manages gift card fees using WC_Cart::add_fee() for block checkout compatibility.
 *
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftGateway;
use stdClass;
use WC_Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Handles gift card operations for WooCommerce Block checkout.
 *
 * Integrates gift card discounts as negative fees in the cart using
 * WC_Cart::add_fee() for proper display and calculation in block checkout.
 */
class HeartlandGiftGatewayBlock {

	/**
	 * Instance of HeartlandGiftGateway for gift card operations.
	 *
	 * @var HeartlandGiftGateway
	 */
	protected $gift_gateway;

	/**
	 * Constructor.
	 *
	 * Initializes the gift gateway and registers hooks for block checkout.
	 *
	 * @param HeartlandGateway|null $heartland_gateway Optional parent gateway instance.
	 */
	public function __construct( ?HeartlandGateway $heartland_gateway = null ) {
		$this->gift_gateway = new HeartlandGiftGateway( $heartland_gateway );
		$this->register_hooks();
	}

	/**
	 * Register WordPress/WooCommerce hooks for block checkout gift card handling.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Hook into cart fee calculation for block checkout
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_gift_card_fees' ), 20 );

		// AJAX handlers for gift card operations in block checkout
		// These use the same action names as the frontend JS expects
		add_action( 'wp_ajax_use_gift_card', array( $this, 'ajax_apply_gift_card' ) );
		add_action( 'wp_ajax_nopriv_use_gift_card', array( $this, 'ajax_apply_gift_card' ) );
		add_action( 'wp_ajax_remove_gift_card', array( $this, 'ajax_remove_gift_card' ) );
		add_action( 'wp_ajax_nopriv_remove_gift_card', array( $this, 'ajax_remove_gift_card' ) );
		add_action( 'wp_ajax_get_applied_gift_cards', array( $this, 'ajax_get_applied_gift_cards' ) );
		add_action( 'wp_ajax_nopriv_get_applied_gift_cards', array( $this, 'ajax_get_applied_gift_cards' ) );
		add_action( 'wp_ajax_get_remaining_balance', array( $this, 'ajax_get_remaining_balance' ) );
		add_action( 'wp_ajax_nopriv_get_remaining_balance', array( $this, 'ajax_get_remaining_balance' ) );
	}

	/**
	 * Add gift card amounts as negative fees to the cart.
	 *
	 * This method is called during cart fee calculation and adds each applied
	 * gift card as a negative fee, which properly reduces the cart total
	 * in WooCommerce Block checkout.
	 *
	 * @param WC_Cart $cart The WooCommerce cart instance.
	 * @return void
	 */
	public function add_gift_card_fees( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( null === WC()->session ) {
			return;
		}

		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );

		if ( ! is_object( $applied_gift_cards ) || count( get_object_vars( $applied_gift_cards ) ) === 0 ) {
			return;
		}

		// Recalculate gift card usage amounts based on current cart total
		$this->recalculate_gift_card_amounts( $cart );

		// Get updated gift cards after recalculation
		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );

		// Verify gift cards still exist after recalculation
		if ( ! is_object( $applied_gift_cards ) || count( get_object_vars( $applied_gift_cards ) ) === 0 ) {
			return;
		}

		foreach ( $applied_gift_cards as $gift_card ) {
			if ( isset( $gift_card->used_amount ) && $gift_card->used_amount > 0 ) {
				// Add as negative fee (discount)
				$cart->add_fee(
					$this->get_gift_card_fee_name( $gift_card ),
					-1 * abs( $gift_card->used_amount ),
					false // Not taxable
				);
			}
		}
	}

	/**
	 * Recalculate gift card usage amounts based on current cart subtotal.
	 *
	 * Updates each gift card's used_amount to ensure it doesn't exceed
	 * the remaining cart balance after other gift cards are applied.
	 *
	 * @param WC_Cart $cart The WooCommerce cart instance.
	 * @return void
	 */
	protected function recalculate_gift_card_amounts( WC_Cart $cart ): void {
		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );

		if ( ! is_object( $applied_gift_cards ) || count( get_object_vars( $applied_gift_cards ) ) === 0 ) {
			return;
		}

		// Calculate remaining total (subtotal + shipping + taxes - existing non-gift-card fees)
		$remaining_total = $this->get_cart_total_before_gift_cards( $cart );

		foreach ( $applied_gift_cards as $gift_card_id => $gift_card ) {
			if ( $remaining_total <= 0 ) {
				$gift_card->used_amount = 0;
			} elseif ( $gift_card->temp_balance <= $remaining_total ) {
				$gift_card->used_amount = $gift_card->temp_balance;
			} else {
				$gift_card->used_amount = $remaining_total;
			}

			$remaining_total -= $gift_card->used_amount;
			$applied_gift_cards->{$gift_card_id} = $gift_card;
		}

		WC()->session->set( 'heartland_gift_card_applied', $applied_gift_cards );
	}

	/**
	 * Calculate the cart total before gift card fees are applied.
	 *
	 * @param WC_Cart $cart The WooCommerce cart instance.
	 * @return float The cart total before gift card deductions.
	 */
	protected function get_cart_total_before_gift_cards( WC_Cart $cart ): float {
		$subtotal      = (float) $cart->get_subtotal();
		$subtotal_tax  = (float) $cart->get_subtotal_tax();
		$shipping      = (float) $cart->get_shipping_total();
		$shipping_tax  = (float) $cart->get_shipping_tax();
		$discount      = (float) $cart->get_discount_total();
		$discount_tax  = (float) $cart->get_discount_tax();

		// Sum existing fees (excluding gift card fees to avoid double-counting)
		$fee_total = 0;
		$fees = $cart->get_fees();
		foreach ( $fees as $fee ) {
			// Skip fees that are gift cards (we're recalculating those)
			if ( strpos( $fee->name, __( 'Gift Card', 'globalpayments-gateway-provider-for-woocommerce' ) ) === false ) {
				$fee_total += (float) $fee->total;
			}
		}

		$total = $subtotal + $subtotal_tax + $shipping + $shipping_tax + $fee_total - $discount - $discount_tax;

		return max( 0, round( $total, 2 ) );
	}

	/**
	 * Get the display name for a gift card fee.
	 *
	 * @param object $gift_card The gift card object.
	 * @return string The formatted gift card name.
	 */
	protected function get_gift_card_fee_name( object $gift_card ): string {
		if ( isset( $gift_card->gift_card_name ) && ! empty( $gift_card->gift_card_name ) ) {
			return esc_html( $gift_card->gift_card_name );
		}

		return esc_html__( 'Gift Card', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * AJAX handler to apply a gift card in block checkout.
	 *
	 * Validates the gift card, checks the balance, and adds it to the session
	 * for fee calculation.
	 *
	 * @return void
	 */
	public function ajax_apply_gift_card(): void {
		// Note: Nonce verification is not used here as the frontend JS does not send one
		// Security is maintained through WordPress AJAX authentication and input sanitization

		$gift_card_number = isset( $_POST['gift_card_number'] ) ?
			wc_clean( wp_unslash( $_POST['gift_card_number'] ) ) : '';
		
		$gift_card_pin = isset( $_POST['gift_card_pin'] ) ? wc_clean( wp_unslash( $_POST['gift_card_pin'] ) ) : '';

		if ( empty( $gift_card_number ) ) {
			echo wp_json_encode( array(
				'error'   => true,
				'message' => esc_html__(
					'Please enter a gift card number.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
			) );
			wp_die();
		}

		// Check balance using the existing gift gateway
		$balance_result = $this->gift_gateway->giftCardBalance( $gift_card_number, $gift_card_pin );

		if ( $balance_result['error'] ) {
			echo wp_json_encode( array(
				'error'   => true,
				'message' => esc_html( $balance_result['message'] ),
			) );
			wp_die();
		}

		$balance = (float) $balance_result['message'];

		if ( $balance <= 0 ) {
			echo wp_json_encode( array(
				'error'   => true,
				'message' => esc_html__(
					'This gift card has a zero balance and cannot be applied.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
			) );
			wp_die();
		}

		// Create gift card object and add to session
		$gift_card = $this->create_gift_card_object( $gift_card_number, $gift_card_pin, $balance );

		// Check if this card is already applied
		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );
		if ( is_object( $applied_gift_cards ) && isset( $applied_gift_cards->{$gift_card->gift_card_id} ) ) {
			echo wp_json_encode( array(
				'error'   => true,
				'message' => esc_html__(
					'This gift card has already been applied to your order.',
					'globalpayments-gateway-provider-for-woocommerce'
				),
			) );
			wp_die();
		}

		// Add gift card to applied cards
		$this->add_gift_card_to_session( $gift_card );

		// Return response in same format as HeartlandGiftGateway::applyGiftCard()
		echo wp_json_encode( array(
			'error'   => 0,
			'balance' => html_entity_decode(
				esc_html(get_woocommerce_currency_symbol() ) ) . esc_html( number_format( $balance, 2 )
			),
		) );
		wp_die();
	}

	/**
	 * AJAX handler to remove a gift card in block checkout.
	 *
	 * @return void
	 */
	public function ajax_remove_gift_card(): void {
		// Note: Nonce verification is not used here as the frontend JS does not send one
		// Security is maintained through WordPress AJAX authentication and input sanitization

		$gift_card_id = isset( $_POST['securesubmit_card_id'] ) ?
			sanitize_title( wp_unslash( $_POST['securesubmit_card_id'] ) ) : '';
		
		// Also check for gift_card_id parameter for backwards compatibility
		if ( empty( $gift_card_id ) ) {
			$gift_card_id = isset( $_POST['gift_card_id'] ) ?
				sanitize_title( wp_unslash( $_POST['gift_card_id'] ) ) : '';
		}

		if ( ! empty( $gift_card_id ) ) {
			$this->remove_gift_card_from_session( $gift_card_id );
		}

		// Match original HeartlandGiftGateway::removeGiftCard() response format
		echo '';
		wp_die();
	}

	/**
	 * AJAX handler to get currently applied gift cards in block checkout.
	 *
	 * @return void
	 */
	public function ajax_get_applied_gift_cards(): void {
		$applied_gift_cards = $this->get_applied_gift_cards();
		$currency_symbol    = html_entity_decode( get_woocommerce_currency_symbol() );
		$response_cards     = array();

		foreach ( $applied_gift_cards as $gift_card ) {
			$response_cards[] = array(
				'id'               => $gift_card->gift_card_id ?? '',
				'name'             => $gift_card->gift_card_name ?? '',
				'balance'          => $gift_card->temp_balance ?? 0,
				'used_amount'      => $gift_card->used_amount ?? 0,
				'formatted_amount' => $currency_symbol . number_format( $gift_card->used_amount ?? 0, 2 ),
			);
		}

		wp_send_json( array(
			'success' => true,
			'cards'   => $response_cards,
		) );
	}

	/**
	 * AJAX handler to get remaining balance after gift cards are applied.
	 *
	 * Returns the cart total that still needs to be paid by credit card.
	 * If this is 0 or less, the order can be paid with gift cards only.
	 *
	 * @return void
	 */
	public function ajax_get_remaining_balance(): void {
		$remaining_balance      = 0;
		$total_gift_card_amount = 0;
		$is_gift_card_only      = false;
		$currency_symbol        = html_entity_decode( get_woocommerce_currency_symbol() );

		if ( WC()->cart ) {
			// Get the cart total (this already includes gift card fees as negative amounts)
			$cart_total = (float) WC()->cart->get_total( 'edit' );

			// Get total gift card amount
			$total_gift_card_amount = $this->get_total_gift_card_amount();

			// The remaining balance is the cart total (which already has gift cards deducted)
			$remaining_balance = max( 0, round( $cart_total, 2 ) );

			// Check if gift cards cover the entire order
			$is_gift_card_only = $remaining_balance <= 0.01 && $total_gift_card_amount > 0;
		}

		wp_send_json( array(
			'success'                 => true,
			'remaining_balance'       => $remaining_balance,
			'formatted_balance'       => $currency_symbol . number_format( $remaining_balance, 2 ),
			'total_gift_card_amount'  => $total_gift_card_amount,
			'is_gift_card_only_order' => $is_gift_card_only,
		) );
	}

	/**
	 * Create a gift card object for session storage.
	 *
	 * @param string $card_number The gift card number.
	 * @param string $card_pin    The gift card PIN.
	 * @param float  $balance     The gift card balance.
	 * @return stdClass The gift card object.
	 */
	protected function create_gift_card_object( string $card_number, string $card_pin, float $balance ): stdClass {
		$gift_card = new stdClass();
		$gift_card->number         = $card_number;
		$gift_card->pin            = $card_pin;
		$gift_card->temp_balance   = $balance;
		$gift_card->gift_card_name = $this->format_gift_card_name( $card_number );
		$gift_card->gift_card_id   = sanitize_title( $gift_card->gift_card_name );
		$gift_card->used_amount    = 0; // Will be calculated during fee calculation

		return $gift_card;
	}

	/**
	 * Format the gift card name for display.
	 *
	 * Shows "Gift Card" followed by the last 5 digits of the card number.
	 *
	 * @param string $card_number The full gift card number.
	 * @return string The formatted gift card name.
	 */
	protected function format_gift_card_name( string $card_number ): string {
		$digits_to_display = 5;
		$last_digits       = substr( $card_number, -1 * $digits_to_display );

		return esc_html__( 'Gift Card', 'globalpayments-gateway-provider-for-woocommerce' ) . ' ' . $last_digits;
	}

	/**
	 * Add a gift card to the session.
	 *
	 * @param stdClass $gift_card The gift card object to add.
	 * @return void
	 */
	protected function add_gift_card_to_session( stdClass $gift_card ): void {
		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );

		if ( ! is_object( $applied_gift_cards ) ) {
			$applied_gift_cards = new stdClass();
		}

		$applied_gift_cards->{$gift_card->gift_card_id} = $gift_card;

		WC()->session->set( 'heartland_gift_card_applied', $applied_gift_cards );

		// Store original total for reference
		$securesubmit_data = WC()->session->get( 'securesubmit_data' );
		if ( ! is_object( $securesubmit_data ) ) {
			$securesubmit_data = new stdClass();
		}
		$securesubmit_data->original_total = $this->get_cart_total_before_gift_cards( WC()->cart );
		WC()->session->set( 'securesubmit_data', $securesubmit_data );
	}

	/**
	 * Remove a gift card from the session.
	 *
	 * @param string $gift_card_id The gift card ID to remove.
	 * @return bool True if the gift card was removed, false otherwise.
	 */
	protected function remove_gift_card_from_session( string $gift_card_id ): bool {
		$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );

		if ( ! is_object( $applied_gift_cards ) || ! isset( $applied_gift_cards->{$gift_card_id} ) ) {
			return false;
		}

		unset( $applied_gift_cards->{$gift_card_id} );

		if ( count( get_object_vars( $applied_gift_cards ) ) > 0 ) {
			WC()->session->set( 'heartland_gift_card_applied', $applied_gift_cards );
		} else {
			WC()->session->__unset( 'heartland_gift_card_applied' );
		}

		return true;
	}

	/**
	 * Get all currently applied gift cards.
	 *
	 * @return array Array of applied gift card objects.
	 */
	public function get_applied_gift_cards(): array {
		$applied_gift_cards = WC()->session ? WC()->session->get( 'heartland_gift_card_applied' ) : null;

		if ( ! is_object( $applied_gift_cards ) ) {
			return array();
		}

		return (array) $applied_gift_cards;
	}

	/**
	 * Get the total amount of all applied gift cards.
	 *
	 * @return float The total gift card amount.
	 */
	public function get_total_gift_card_amount(): float {
		$applied_gift_cards = $this->get_applied_gift_cards();
		$total = 0;

		foreach ( $applied_gift_cards as $gift_card ) {
			if ( isset( $gift_card->used_amount ) ) {
				$total += (float) $gift_card->used_amount;
			}
		}

		return round( $total, 2 );
	}

	/**
	 * Clear all gift cards from the session.
	 *
	 * @return void
	 */
	public function clear_all_gift_cards(): void {
		if ( WC()->session ) {
			WC()->session->__unset( 'heartland_gift_card_applied' );
			WC()->session->__unset( 'securesubmit_gift_card_object' );
			WC()->session->__unset( 'securesubmit_data' );
		}
	}

	/**
	 * Check if gift cards are currently applied.
	 *
	 * @return bool True if gift cards are applied, false otherwise.
	 */
	public function has_applied_gift_cards(): bool {
		return count( $this->get_applied_gift_cards() ) > 0;
	}

	/**
	 * Get data for front-end consumption in block checkout.
	 *
	 * @return array Array of gift card data for the front-end.
	 */
	public function get_frontend_data(): array {
		$applied_gift_cards = $this->get_applied_gift_cards();
		$frontend_data = array();

		foreach ( $applied_gift_cards as $gift_card ) {
			$frontend_data[] = array(
				'id'          => $gift_card->gift_card_id ?? '',
				'name'        => $gift_card->gift_card_name ?? '',
				'balance'     => $gift_card->temp_balance ?? 0,
				'used_amount' => $gift_card->used_amount ?? 0,
			);
		}

		return $frontend_data;
	}
}
