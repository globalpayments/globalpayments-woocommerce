<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftGateway;
use WC_Order;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftCardOrder;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class HeartlandGateway extends AbstractGateway {
	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_heartland';

	public $gateway_provider = GatewayProvider::PORTICO;

	/**
	 * Live Merchant location public API key
	 *
	 * Used for single-use tokenization on frontend
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * Live Merchant location secret API key
	 *
	 * Used for gateway transactions on backend
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Sandbox Merchant location public API key
	 *
	 * Used for single-use tokenization on frontend
	 *
	 * @var string
	 */
	public $sandbox_public_key;

	/**
	 * Sandbox Merchant location secret API key
	 *
	 * Used for gateway transactions on backend
	 *
	 * @var string
	 */
	public $sandbox_secret_key;

	/**
	 * Should live payments be accepted
	 *
	 * @var bool
	 */
	public $is_production;

	/**
	 * Allows payment via Heartland Marketing Solutions (gift cards)
	 *
	 * @var bool
	 */
	public $allow_gift_cards;

	/**
	 * Should debug
	 *
	 * @var bool
	 */
	public $debug;

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Global Payments', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Global Payments Portico gateway.', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'onlinepayments@heartland.us';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production' => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Get your API keys from your Online Payments account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
			),
			'public_key' => array(
				'title'       => __( 'Live Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'secret_key' => array(
				'title'       => __( 'Live Secret Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'sandbox_public_key' => array(
				'title'       => __( 'Sandbox Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_secret_key' => array(
				'title'       => __( 'Sandbox Secret Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
			'debug' => array(
				'title'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Enable Logging', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Log all requests to and from the gateway. This can also log private data and should only be enabled in a development or stage environment.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'allow_gift_cards' => array(
				'title'				=> __( 'Enable Gift Cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'				=> __( 'Allow customers to use gift cards to pay for purchases in full or in part.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'				=> 'checkbox',
				'description'		=> sprintf(
					__( 'This will display a gift card entry field directly below the credit card entry area.', 'globalpayments-gateway-provider-for-woocommerce' )
				),
				'default'			=> 'no'
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'publicApiKey' => $this->get_credential_setting( 'public_key' ),
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'secretApiKey'  => $this->get_credential_setting( 'secret_key' ),
			'versionNumber' => '1510',
			'developerId'   => '002914',
			'debug'         => $this->debug,
		);
	}

	protected function add_hooks() {
		parent::add_hooks();

		if ( $this->allow_gift_cards === true ) {
			// Skip classic gift card hooks if block checkout is enabled
			// HeartlandGiftGatewayBlock handles gift cards for block checkout
			if ( $this->is_checkout_block_enabled() ) {
				// Only register order-related hooks that are needed for both checkout types
				$gcthing = new HeartlandGiftCardOrder();
				add_filter(
					'woocommerce_get_order_item_totals',
					array( $gcthing, 'addItemsToPostOrderDisplay' ),
					PHP_INT_MAX - 1,
					2
				);
				// Classic checkout hook (fallback)
				add_action(
					'woocommerce_checkout_order_processed',
					array( $gcthing, 'processGiftCardsZeroTotal' ),
					PHP_INT_MAX,
					2
				);
				// Block checkout hook - uses different signature (WC_Order instead of order_id + posted)
				add_action(
					'woocommerce_store_api_checkout_order_processed',
					array( $gcthing, 'processGiftCardsZeroTotalBlockCheckout' ),
					PHP_INT_MAX,
					1
				);
				add_action( 'wp_enqueue_scripts', function () {
					wp_enqueue_style(
						'heartland-gift-cards',
						Plugin::get_url( '/assets/frontend/css/heartland-gift-cards.css' )
					);
				} );
				return;
			}

			// Classic checkout gift card handling
		    $HeartlandGiftGateway = new HeartlandGiftGateway( $this );

			add_action(
				'wp_ajax_use_gift_card',
				array( $HeartlandGiftGateway, 'applyGiftCard' )
			);
			add_action(
				'wp_ajax_nopriv_use_gift_card',
				array( $HeartlandGiftGateway, 'applyGiftCard' )
			);
			add_action(
				'woocommerce_review_order_before_order_total',
				array( $HeartlandGiftGateway, 'addGiftCards' )
			);
			add_action(
				'woocommerce_cart_totals_before_order_total',
				array( $HeartlandGiftGateway, 'addGiftCards' )
			);
			add_filter(
				'woocommerce_calculated_total',
				array( $HeartlandGiftGateway, 'updateOrderTotal' ),
				10,
				2
			);
			add_action(
				'wp_ajax_nopriv_remove_gift_card',
				array( $HeartlandGiftGateway, 'removeGiftCard' )
			);
			add_action( 'wp_ajax_remove_gift_card', array( $HeartlandGiftGateway, 'removeGiftCard' ) );
			add_action( 'wp_enqueue_scripts', function () {
				wp_enqueue_style(
					'heartland-gift-cards',
					Plugin::get_url( '/assets/frontend/css/heartland-gift-cards.css' )
				);
			} );

			$gcthing = new HeartlandGiftCardOrder();

			add_filter(
				'woocommerce_get_order_item_totals',
				array( $gcthing, 'addItemsToPostOrderDisplay' ),
				PHP_INT_MAX - 1,
				2
			);
			add_action(
				'woocommerce_checkout_order_processed',
				array( $gcthing, 'processGiftCardsZeroTotal' ),
				PHP_INT_MAX,
				2
			);
		}
	}

	/**
	 * Check if the WooCommerce checkout page uses the block-based checkout.
	 *
	 * @return bool True if block checkout is enabled, false otherwise.
	 */
	protected function is_checkout_block_enabled(): bool {
		$checkout_page_id = wc_get_page_id( 'checkout' );

		if ( $checkout_page_id && $checkout_page_id > 0 ) {
			$checkout_page = get_post( $checkout_page_id );
			if (
				$checkout_page && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout_page )
			) {
				return true;
			}
		}

		return false;
	}

	protected function is_transaction_active( TransactionSummary $details ) {
		// @phpcs:ignore WordPress.NamingConventions.ValidVariableName
		return 'A' === $details->transactionStatus;
	}

	/**
	 * returns decline message for display to customer
	 *
	 * @param string $response_code
	 *
	 * @return string
	 */
	public function get_decline_message( string $response_code ) {
		switch ( $response_code ) {
			case '02':
			case '03':
			case '04':
			case '05':
			case '41':
			case '43':
			case '44':
			case '51':
			case '56':
			case '61':
			case '62':
			case '63':
			case '65':
			case '78':
				return 'The card was declined.';
			case '06':
			case '07':
			case '12':
			case '15':
			case '19':
			case '52':
			case '53':
			case '57':
			case '58':
			case '76':
			case '77':
			case '96':
			case 'EC':
				return 'An error occured while processing the card.';
			case '13':
				return 'Must be greater than or equal 0.';
			case '14':
				return 'The card number is incorrect.';
			case '54':
				return 'The card has expired.';
			case '55':
				return 'The pin is invalid.';
			case '75':
				return 'Maximum number of pin retries exceeded.';
			case '80':
				return 'Card expiration date is invalid.';
			case '86':
				return 'Can\'t verify card pin number.';
			case '91':
				return 'The card issuer timed-out.';
			case 'EB':
			case 'N7':
				return 'The card\'s security code is incorrect.';
			case 'FR':
				return 'Possible fraud detected.';
			default:
				return 'An error occurred while processing the card.';
		}
	}

	/**
	 * Add gift card fields if enabled
	 *
	 */
	public function payment_fields() {
		parent::payment_fields();

		if ( $this->allow_gift_cards === true ) {
			$path = dirname( plugin_dir_path( __FILE__ ) );

			include_once  $path . '/../assets/frontend/HeartlandGiftFields.php';
		}
	}

	/**
	 * Handle payment functions
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order                  = new WC_Order( $order_id );
		$applied_gift_cards     = WC()->session ? WC()->session->get( 'heartland_gift_card_applied' ) : null;
		$has_applied_gift_cards = is_object( $applied_gift_cards ) && count( get_object_vars( $applied_gift_cards ) ) > 0;

		// Check if this is a gift-card-only order (order total is $0 or effectively covered by gift cards)
		$order_total = (float) $order->get_total();
		$is_gift_card_only_order = $has_applied_gift_cards && $order_total <= 0.01;

		// If gift cards cover the entire order, skip credit card processing
		if ( $is_gift_card_only_order ) {
			return $this->process_gift_card_only_payment( $order_id, $order );
		}

		$request       = $this->prepare_request( $this->payment_action, $order );
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		// Charge HPS gift cards if CC trans succeeds
		if ( $is_successful && $has_applied_gift_cards ) {
			$gift_card_order_placement = new HeartlandGiftCardOrder();
			$gift_payments_successful = $gift_card_order_placement->processGiftCardPayment( $order_id );

			// reverse the CC transaction if GC transactions didn't didn't succeed
			if ( !$gift_payments_successful ) {
				if ( $gift_card_order_placement !== false ) {

				// hook directly into GP SDK to avoid collisions with the existing request
				Transaction::fromId( $response->transactionReference->transactionId )
					->reverse( $request->order->data[ 'total' ] )
					->execute();

				$is_successful = false;
				}
			}
		}

		$note_text = sprintf(
			'%1$s%2$s %3$s. Order created with Transaction ID: %4$s.',
			get_woocommerce_currency_symbol($order->get_currency()),
			$order->get_total(),
			$this->payment_action,
			$order->get_transaction_id()
		);

		$order->add_order_note($note_text);

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}

	/**
	 * Process a gift-card-only payment (no credit card charge needed).
	 *
	 * @param int      $order_id The order ID.
	 * @param WC_Order $order    The order object.
	 *
	 * @return array Payment result.
	 */
	protected function process_gift_card_only_payment( int $order_id, WC_Order $order ): array {
		$is_successful = false;

		try {
			$gift_card_order_placement = new HeartlandGiftCardOrder();
			$gift_payments_successful  = $gift_card_order_placement->processGiftCardPayment( $order_id );

			if ( $gift_payments_successful ) {
				$is_successful = true;

				// Mark order as paid
				$order->payment_complete();

				$note_text = sprintf(
					/* translators: %1$s currency symbol, %2$s order total */
					__( 'Order paid in full with gift cards. Total: %1$s%2$s', 'globalpayments-gateway-provider-for-woocommerce' ),
					get_woocommerce_currency_symbol( $order->get_currency() ),
					wc_format_decimal( $order->get_total(), 2 )
				);
				$order->add_order_note( $note_text );
			}
		} catch ( \Exception $e ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s error message */
					__( 'Gift card payment failed: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
					$e->getMessage()
				)
			);
			wc_add_notice( $e->getMessage(), 'error' );
		}

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}
}
