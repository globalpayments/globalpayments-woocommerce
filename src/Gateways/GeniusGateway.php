<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\PayOrderTrait;

defined( 'ABSPATH' ) || exit;

class GeniusGateway extends AbstractGateway {
	use PayOrderTrait;
	/**
	 * Gateway ID
	 */
	const GATEWAY_ID = 'globalpayments_genius';

	public $gateway_provider = GatewayProvider::GENIUS;

	/**
	 * Live Merchant location's Merchant Name
	 *
	 * @var string
	 */
	public $merchant_name;

	/**
	 * Live Merchant location's Site ID
	 *
	 * @var string
	 */
	public $merchant_site_id;

	/**
	 * Live Merchant location's Merchant Key
	 *
	 * @var string
	 */
	public $merchant_key;

	/**
	 * Live Merchant location's Web API Key
	 *
	 * @var string
	 */
	public $web_api_key;

	/**
	 * Sandbox Merchant location's Merchant Name
	 *
	 * @var string
	 */
	public $sandbox_merchant_name;

	/**
	 * Sandbox Merchant location's Site ID
	 *
	 * @var string
	 */
	public $sandbox_merchant_site_id;

	/**
	 * Sandbox Merchant location's Merchant Key
	 *
	 * @var string
	 */
	public $sandbox_merchant_key;

	/**
	 * Sandbox Merchant location's Web API Key
	 *
	 * @var string
	 */
	public $sandbox_web_api_key;

	/**
	 * Should live payments be accepted
	 *
	 * @var bool
	 */
	public $is_production;

	public function __construct( $is_provider = false ) {
		parent::__construct( $is_provider );
		array_push( $this->supports, 'globalpayments_hosted_fields' );
	}

	public function configure_method_settings() {
		$this->id                 = self::GATEWAY_ID;
		$this->method_title       = __( 'Global Payments Genius', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Global Payments Genius gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'securesubmitcert@e-hps.com';
	}

	public function get_gateway_form_fields() {
		return array(
			'is_production'    => array(
				'title'   => __( 'Live Mode', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'checkbox',
				'description' => __( 'Get your credentials from your Global Payments Genius account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default' => 'no',
			),
			'merchant_name'    => array(
				'title'   => __( 'Live Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'live-toggle',
			),
			'merchant_site_id' => array(
				'title'   => __( 'Live Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'live-toggle',
			),
			'merchant_key'     => array(
				'title'   => __( 'Live Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'class' => 'live-toggle',
			),
			'web_api_key'      => array(
				'title'       => __( 'Live Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'live-toggle',
			),
			'sandbox_merchant_name'    => array(
				'title'   => __( 'Sandbox Merchant Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_merchant_site_id' => array(
				'title'   => __( 'Sandbox Merchant Site ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'text',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_merchant_key'     => array(
				'title'   => __( 'Sandbox Merchant Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'password',
				'default' => '',
				'class' => 'sandbox-toggle',
			),
			'sandbox_web_api_key'      => array(
				'title'       => __( 'Sandbox Web API Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'class' => 'sandbox-toggle',
			),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'webApiKey' => $this->get_credential_setting( 'web_api_key' ),
			'env'       => $this->is_production ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'merchantName'   => $this->get_credential_setting( 'merchant_name' ),
			'merchantSiteId' => $this->get_credential_setting( 'merchant_site_id' ),
			'merchantKey'    => $this->get_credential_setting( 'merchant_key' ),
			'environment'    => $this->is_production ? Environment::PRODUCTION : Environment::TEST,
		);
	}

	/**
	 * Add Genius-specific hooks for subscriptions and admin Pay for Order.
	 *
	 * @return void
	 */
	protected function add_hooks(): void {
		parent::add_hooks();

		// Admin Pay for Order hooks.
		if ( is_admin() && current_user_can( 'edit_shop_orders' ) ) {
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'pay_order_modal' ), 99 );
			add_filter( 'globalpayments_secure_payment_fields_styles', array( $this, 'pay_order_modal_secure_payment_fields_styles' ) );
		}
		add_action( 'woocommerce_api_globalpayments_pay_order', array( $this, 'pay_order_modal_process_payment' ), 99 );
		add_action( 'woocommerce_api_globalpayments_get_payment_methods', array( $this, 'pay_order_modal_get_payment_methods' ) );

		// Subscription hooks.
		if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
			return;
		}

		// Register renewal payment hook.
		add_action(
			'woocommerce_scheduled_subscription_payment_' . $this->id,
			array( $this, 'renew_subscription' ),
			10,
			2
		);

		// Clean up token when subscription is cancelled.
		add_action(
			'woocommerce_subscription_status_pending-cancel_to_cancelled',
			array( $this, 'handle_subscription_cancellation' )
		);
	}

	/**
	 * Process payment for both regular and subscription orders.
	 *
	 * For subscription orders, performs a BoardCard (Verify) transaction to obtain
	 * a multi-use vault token, then charges the customer via the SDK using that token.
	 * The vault token is persisted on the parent order for future renewal payments.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		// "Change payment method" flow: WC passes a WC_Subscription as the order.
		// Only run BoardCard (no Sale) and persist the new vault token.
		$is_change_payment_method = function_exists( 'wcs_is_subscription' )
			&& wcs_is_subscription( $order_id );

		if ( $is_change_payment_method ) {
			if ( ! $order instanceof \WC_Subscription ) {
				return array(
					'result'   => 'failure',
					'redirect' => false,
				);
			}

			return $this->change_subscription_payment_method( $order );
		}

		$is_subscription = function_exists( 'wcs_order_contains_subscription' )
			&& wcs_order_contains_subscription( $order );

		if ( ! $is_subscription ) {
			return parent::process_payment( $order_id );
		}

		try {
			// STEP 1: Tokenize the card (BoardCard) to obtain a vault token.
			$verify_request  = $this->prepare_request( parent::TXN_TYPE_VERIFY, $order );
			$verify_response = $this->submit_request( $verify_request );

			if ( empty( $verify_response->token ) ) {
				throw new Exception(
					esc_html__(
						'Tokenization failed: No vault token returned from BoardCard.',
						'globalpayments-gateway-provider-for-woocommerce'
					)
				);
			}

			$vault_token = (string) $verify_response->token;

			// STEP 2: Charge using the vault token via the SDK SubscriptionRequest.
			$sale_request  = $this->prepare_request(
				parent::TXN_TYPE_SUBSCRIPTION_PAYMENT,
				$order,
				array( 'multi_use_token' => $vault_token )
			);
			$sale_response = $this->client->submit_request( $sale_request );
			$is_successful = $this->handle_response( $sale_request, $sale_response );

			if ( ! $is_successful ) {
				return array(
					'result'   => 'failure',
					'redirect' => false,
				);
			}

			// Persist vault token on the order for future renewal payments.
			$order->update_meta_data( '_GP_multi_use_token', $vault_token );
			$order->save();

			// Mark payment as captured (HPOS aware).
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->add_meta_data( '_globalpayments_payment_captured', 'is_captured', true );
			} else {
				add_post_meta( $order->get_id(), '_globalpayments_payment_captured', 'is_captured', true );
			}

			$note_text = sprintf(
				/* translators: 1: currency symbol, 2: amount, 3: localized "charged", 4: transaction ID, 5: truncated vault token */
				esc_html__( '%1$s%2$s %3$s. Transaction ID: %4$s. Vault token saved for renewals (Token: %5$s).', 'globalpayments-gateway-provider-for-woocommerce' ),
				get_woocommerce_currency_symbol( $order->get_currency() ),
				$order->get_total(),
				esc_html__( 'charged', 'globalpayments-gateway-provider-for-woocommerce' ),
				$order->get_transaction_id(),
				substr( $vault_token, 0, 15 ) . '...'
			);
			$order->add_order_note( $note_text );

			// Mark order as paid; transitions status from pending to processing/completed.
			$transaction_id = isset( $sale_response->transactionReference->transactionId )
				? $sale_response->transactionReference->transactionId
				: '';
			$order->payment_complete( $transaction_id );

			if ( function_exists( 'WC' ) && WC()->cart ) {
				WC()->cart->empty_cart();
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			$error_message = sprintf(
				/* translators: %s: error message */
				esc_html__( 'Genius subscription payment failed: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$e->getMessage()
			);
			$order->add_order_note( $error_message );

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $error_message, 'error' );
			}

			return array(
				'result'   => 'failure',
				'redirect' => false,
			);
		}
	}

	/**
	 * Handle subscription renewal payments.
	 *
	 * Charges the customer using the multi-use vault token stored on the parent
	 * order. If no token is present the renewal is marked as failed.
	 *
	 * @param float           $amount_to_charge Renewal amount.
	 * @param int|\WC_Order   $renewal_order    Renewal order or order ID.
	 *
	 * @return bool
	 */
	public function renew_subscription( float $amount_to_charge, $renewal_order ): bool {
		if ( ! $renewal_order instanceof \WC_Order ) {
			$renewal_order = wc_get_order( $renewal_order );
		}

		if ( ! $renewal_order ) {
			return false;
		}

		// Locate the parent order that holds the saved vault token.
		$renewal_subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		$parent_order = false;

		foreach ( $renewal_subscriptions as $subscription ) {
			if ( $subscription->get_parent_id() ) {
				$parent_order = wc_get_order( $subscription->get_parent_id() );
				break;
			}
		}

		if ( ! $parent_order || ! $parent_order->get_meta( '_GP_multi_use_token' ) ) {
			$message = esc_html__(
				'Genius subscription renewal failed: no reusable payment token found on parent order.',
				'globalpayments-gateway-provider-for-woocommerce'
			);
			$renewal_order->add_order_note( $message );
			$renewal_order->update_status( 'failed', $message );
			$renewal_order->save();
			return false;
		}

		try {
			$request = $this->prepare_request(
				parent::TXN_TYPE_SUBSCRIPTION_PAYMENT,
				$renewal_order,
				array( 'multi_use_token' => $parent_order->get_meta( '_GP_multi_use_token' ) )
			);

			$response         = $this->client->submit_request( $request );
			$client_trans_ref = isset( $response->transactionReference->clientTransactionId )
				? $response->transactionReference->clientTransactionId
				: 'N/A';

			if ( ! parent::handle_response( $request, $response ) ) {
				$renewal_order->add_order_note(
					sprintf(
						/* translators: %s: client transaction reference */
						esc_html__( 'Genius subscription renewal payment failed. Transaction Reference: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
						$client_trans_ref
					)
				);
				$renewal_order->update_status( 'failed' );
				$renewal_order->save();
				return false;
			}

			$renewal_order->add_order_note(
				sprintf(
					/* translators: %s: client transaction reference */
					esc_html__( 'Genius subscription renewal successful. Transaction Reference: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
					$client_trans_ref
				)
			);
			$renewal_order->payment_complete();
			$renewal_order->save();

			return true;
		} catch ( Exception $e ) {
			$message = sprintf(
				/* translators: %s: error message */
				esc_html__( 'Genius subscription renewal payment failed: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$e->getMessage()
			);
			$renewal_order->add_order_note( $message );
			$renewal_order->update_status( 'failed', $message );
			$renewal_order->save();
			return false;
		}
	}

	/**
	 * Remove the stored vault token when a subscription is cancelled.
	 *
	 * @param \WC_Subscription $subscription Cancelled subscription.
	 *
	 * @return void
	 */
	public function handle_subscription_cancellation( \WC_Subscription $subscription ): void {
		if ( ! $subscription || $subscription->get_payment_method() !== $this->id ) {
        return;
		}

		$parent_order = wc_get_order( $subscription->get_parent_id() );
		if ( ! $parent_order ) {
			return;
		}

		$parent_order->delete_meta_data( '_GP_multi_use_token' );
		$parent_order->save();
	}

	/**
	 * Handle the WooCommerce Subscriptions "change payment method" flow.
	 *
	 * Performs only a BoardCard (Verify) — no Sale — and stores the new vault
	 * token on the subscription and its parent order so that future renewals
	 * use the new card.
	 *
	 * @param \WC_Subscription $subscription Subscription whose payment method is changing.
	 *
	 * @return array
	 */
	private function change_subscription_payment_method( \WC_Subscription $subscription ): array {
		try {
			$verify_request  = $this->prepare_request( parent::TXN_TYPE_VERIFY, $subscription );
			$verify_response = $this->submit_request( $verify_request );

			if ( empty( $verify_response->token ) ) {
				throw new Exception(
					esc_html__(
						'Card verification failed: no vault token returned.',
						'globalpayments-gateway-provider-for-woocommerce'
					)
				);
			}

			$vault_token = (string) $verify_response->token;

			// Persist new token on the subscription itself.
			$subscription->update_meta_data( '_GP_multi_use_token', $vault_token );
			$subscription->save();

			// Also persist on the parent order so renew_subscription() picks it up.
			$parent_order = $subscription->get_parent();
			if ( $parent_order ) {
				$parent_order->update_meta_data( '_GP_multi_use_token', $vault_token );
				$parent_order->save();
			}

			$subscription->add_order_note(
				sprintf(
					/* translators: %s: truncated vault token */
					esc_html__( 'Payment method updated. New vault token saved (Token: %s).', 'globalpayments-gateway-provider-for-woocommerce' ),
					substr( $vault_token, 0, 15 ) . '...'
				)
			);

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			);
		} catch ( Exception $e ) {
			$error_message = sprintf(
				/* translators: %s: error message */
				esc_html__( 'Payment method change failed: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
				$e->getMessage()
			);
			$subscription->add_order_note( $error_message );

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $error_message, 'error' );
			}

			return array(
				'result'   => 'failure',
				'redirect' => false,
			);
		}
	}

	/**
	 * Overrides parent class method
	 *
	 * @param int    $order_id
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws ApiException
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool {
		$order = wc_get_order( $order_id );

		$request = $this->prepare_request( self::TXN_TYPE_REFUND, $order );

		if ( null != $amount ) {
			$amount = str_replace( ',', '.', $amount );
			$amount = number_format( (float) round( $amount, 2, PHP_ROUND_HALF_UP ), 2, '.', '' );
			if ( ! is_numeric( $amount ) ) {
				throw new Exception( esc_html__( 'Refund amount must be a valid number', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		}
		$request->set_request_data( array(
			'refund_amount' => $amount,
			'refund_reason' => $reason,
		) );
		$request_args = $request->get_args();
		if ( 0 >= (float)$request_args[ RequestArg::AMOUNT ] ) {
			throw new Exception( esc_html__( 'Refund amount must be greater than zero.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );

		if ( $is_successful ) {
			$note_text = sprintf(
                /* translators: %1$s%2$s was reversed or refunded. Transaction ID: %3$s */
				esc_html__( '%1$s%2$s was reversed or refunded. Transaction ID: %3$s ', 'globalpayments-gateway-provider-for-woocommerce' ),
				get_woocommerce_currency_symbol(), $amount, $response->transactionReference->transactionId
			);

			$order->add_order_note( $note_text );
		}

		return $is_successful;
	}
}
