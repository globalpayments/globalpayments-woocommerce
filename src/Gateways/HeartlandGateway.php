<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftGateway;
use WC_Order;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftCardOrder;

defined( 'ABSPATH' ) || exit;

class HeartlandGateway extends AbstractGateway {
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
	 * Allows payment via Heartland Marketing Solutions (gift cards)
	 *
	 * @var bool
	 */
	public $allow_gift_cards;

	public function configure_method_settings() {
		$this->id                 = 'globalpayments_heartland';
		$this->method_title       = __( 'Heartland', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Heartland Portico gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'onlinepayments@heartland.us';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production' => array(
				'title'       => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Get your API keys from your Heartland Online Payments account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => 'no',
			),
			'public_key' => array(
				'title'       => __( 'Live Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
			),
			'secret_key' => array(
				'title'       => __( 'Live Secret Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
			),
			'sandbox_public_key' => array(
				'title'       => __( 'Sandbox Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
			),
			'sandbox_secret_key' => array(
				'title'       => __( 'Sandbox Secret Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
			),
			'allow_gift_cards' => array(
				'title'				=> __( 'Enable Gift Cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'				=> __( 'Allow customers to use gift cards to pay for purchases in full or in part.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'				=> 'checkbox',
				'description'		=> sprintf(
					__( 'This will display a gift card entry field directly below the credit card entry area.' )
				),
				'default'			=> 'no'
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'publicApiKey' => $this->public_key,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'secretApiKey' => $this->secret_key,
			'versionNumber' => '1510',
			'developerId' => '002914'
		);
	}

	protected function add_hooks() {
		parent::add_hooks();

		if ($this->allow_gift_cards === true) {
			$HeartlandGiftGateway = new HeartlandGiftGateway();

			add_action('wp_ajax_nopriv_use_gift_card',                array($HeartlandGiftGateway, 'applyGiftCard'));
			add_action('wp_ajax_use_gift_card',                       array($HeartlandGiftGateway, 'applyGiftCard'));
			add_action('woocommerce_review_order_before_order_total', array($HeartlandGiftGateway, 'addGiftCards'));
			add_action('woocommerce_cart_totals_before_order_total',  array($HeartlandGiftGateway, 'addGiftCards'));
			add_filter('woocommerce_calculated_total',                array($HeartlandGiftGateway, 'updateOrderTotal'), 10, 2);
			add_action('wp_ajax_nopriv_remove_gift_card',             array($HeartlandGiftGateway, 'removeGiftCard'));
			add_action('wp_ajax_remove_gift_card',                    array($HeartlandGiftGateway, 'removeGiftCard'));

			$gcthing = new HeartlandGiftCardOrder();

			add_filter('woocommerce_get_order_item_totals', array( $gcthing, 'addItemsToPostOrderDisplay'), PHP_INT_MAX - 1, 2);
			add_action('woocommerce_checkout_order_processed', array( $gcthing, 'processGiftCardsZeroTotal'), PHP_INT_MAX, 2);
		}
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
		switch ($response_code) {
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
			$path = dirname(plugin_dir_path(__FILE__));

			include_once  $path . '/../assets/frontend/HeartlandGiftFields.php';
			wp_enqueue_style('heartland-gift-cards', $path . '/../assets/frontend/css/heartland-gift-cards.css');
		}
	}

	/**
	 * Handle payment functions
	 *
	 * @param int $order_id
	 *
	 * @return array	 * 
	 */
	public function process_payment( $order_id ) {
		$order         = new WC_Order( $order_id );
		$request       = $this->prepare_request( $this->payment_action, $order );
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		// Charge HPS gift cards if CC trans succeeds
		if ( $is_successful && !empty( WC()->session->get( 'heartland_gift_card_applied' ) ) ) {
			$gift_card_order_placement = new HeartlandGiftCardOrder();
			$gift_payments_successful = $gift_card_order_placement->processGiftCardPayment( $order_id );

			// reverse the CC transaction if GC transactions didn't didn't succeed
			if (!$gift_payments_successful) {			
				if ($gift_card_order_placement !== false) {

				// hook directly into GP SDK to avoid collisions with the existing request
				Transaction::fromId( $response->transactionReference->transactionId )
					->reverse( $request->order->data[ 'total' ] )
					->execute();

				$is_successful = false;
				}
			} 
		}

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}
}
