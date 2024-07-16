<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\BankPaymentType;
use GlobalPayments\Api\Gateways\OpenBankingProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\MulticheckboxTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\TransactionInfoTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class OpenBanking extends AbstractAsyncPaymentMethod
{
	use TransactionInfoTrait;
	use MulticheckboxTrait;

	public const PAYMENT_METHOD_ID = 'globalpayments_bankpayment';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $default_title = 'Bank Payment';

	/**
	 * @var string
	 */
	public $account_number;
	public $account_name;
	public $sort_code;
	public $countries;
	public $currencies;
	public $iban;

	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'woocommerce_settings_set_payment_action' ) );
		add_action( 'woocommerce_order_actions', array( $this, 'remove_capture_order_action' ), 10, 2 );
	}

	/**
	 * Enqueues OB scripts from Global Payments.
	 *
	 * @return
	 */
	public function enqueue_payment_scripts() {
		wp_enqueue_style(
			'globalpayments-openbanking',
			Plugin::get_url( '/assets/frontend/css/globalpayments-openbanking.css' ),
			array(),
			Plugin::get_version()
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_payment_action_options() {
		return array(
			AbstractGateway::TXN_TYPE_SALE => __( 'Authorize + Capture', 'globalpayments-gateway-provider-for-woocommerce' ),
		);
	}

	/**
	 * Force payment action to `charge`.
	 *
	 * @param $settings
	 *
	 * @return mixed
	 */
	public function woocommerce_settings_set_payment_action( $settings ) {
		$settings['payment_action'] = AbstractGateway::TXN_TYPE_SALE;

		return $settings;
	}

	/**
	 * Remove delayed capture functionality to the "Edit Order" screen
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function remove_capture_order_action( $actions, $order ) {
		if ( $order->get_data()['payment_method'] === self::PAYMENT_METHOD_ID ) {
			unset( $actions['capture_credit_card_authorization'] );
		}

		return $actions;
	}

	/**
	 * @inheritdoc
	 */
	public function get_request_type() {
		return AbstractGateway::TXN_TYPE_OB_AUTHORIZATION;
	}

	public function is_available() {
		if ( false === parent::is_available() ) {
			return false;
		}
		$currency = get_woocommerce_currency();
		if ( ! in_array( $currency, $this->currencies ) ) {
			return false;
		}

		$method_availability = $this->get_method_availability();
		if ( ! isset( $method_availability[ $currency ] ) ) {
			return false;
		}
		// Currency is available and no countries added in the admin panel
		if ( empty( $method_availability[ $currency ] ) ) {
			return true;
		}

		if ( WC()->cart ) {
			$customer = WC()->cart->get_customer();
			if ( ! in_array( $customer->get_billing_country(), $method_availability[ $currency ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns provider and notifications endpoints.
	 *
	 * @return array
	 */
	public function get_provider_endpoints() {
		return array(
			'returnUrl' => WC()->api_request_url( $this->id . '_return', true ),
			'statusUrl' => WC()->api_request_url( $this->id . '_status', true ),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment( $order_id ) {
		// At this point, order should be placed in 'Pending Payment', but products should still be visible in the cart
		$order = wc_get_order( $order_id );

		try {
			// 1. Initiate the payment
			$gateway_response = $this->initiate_payment( $order );

			// Add order note  prior to customer redirect
			$note_text = sprintf(
				__( '%1$s payment initiated with %3$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce' ),
				wc_price( $order->get_total() ),
				$gateway_response->transactionId,
				__('Bank Payment', 'globalpayments-gateway-provider-for-woocommerce' )
			);

			$order->add_order_note( $note_text );
			$order->set_transaction_id( $gateway_response->transactionId );
			$order->save();

			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( '_globalpayments_payment_action', $this->payment_action );
			} else {
				update_post_meta( $order_id, '_globalpayments_payment_action', $this->payment_action );
			}

			// 2. Redirect the customer
			return array(
				'result'   => 'success',
				'redirect' => $gateway_response->bankPaymentResponse->redirectUrl
			);
		} catch ( \Exception $e ) {
			wc_get_logger()->error( $e->getMessage() );
			throw new \Exception( Utils::map_response_code_to_friendly_message() );
		}
	}

	/**
	 * Initiate the payment.
	 *
	 * @param WC_Order $order
	 *
	 * @throws \GlobalPayments\Api\Entities\Exceptions\ApiException
	 */
	private function initiate_payment( WC_Order $order ) {
		$request = $this->gateway->prepare_request( AbstractGateway::TXN_TYPE_OB_AUTHORIZATION, $order );

		$provider = OpenBankingProvider::getBankPaymentType(get_woocommerce_currency());
		$settings = [
			'iban'              => $this->iban,
			'account_number'    => $this->account_number,
			'account_name'      => $this->account_name,
			'sort_code'         => $provider === BankPaymentType::FASTERPAYMENTS ? $this->sort_code : '',
			'countries'         => $this->countries,
			'currencies'        => $this->currencies,
		];
		$request->set_request_data( array(
			'globalpayments_openbanking' => $this->get_provider_endpoints(),
			'settings' => $settings,
		) );

		$gateway_response = $this->gateway->client->submit_request( $request );

		$this->gateway->handle_response( $request, $gateway_response );

		return $gateway_response;
	}
	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->default_title      = __( 'Bank Payment', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Bank Payment', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Bank Payments via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_payment_method_form_fields() {
		return array(
			'account_number' => array(
				'title'       => __( 'Account Number', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Account number, for bank transfers within the UK (UK to UK bank). Only required if no bank details are stored on account.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'account_name'   => array(
				'title'       => __( 'Account Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The name of the individual or business on the bank account. Only required if no bank details are stored on account.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'iban'           => array(
				'title'       => __( 'IBAN', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Only required for EUR transacting merchants.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'sort_code'      => array(
				'title'       => __( 'Sort Code', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Six digits which identify the bank and branch of an account. Included with the Account Number for UK to UK bank transfers. Only required if no bank details are stored on account.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'countries'      => array(
				'title'       => __( 'Countries', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Allows you to input a COUNTRY or string of COUNTRIES to limit what is shown to the customer. Including a country overrides your default account configuration. <br/><br/>
											     Format: List of ISO 3166-2 (two characters) codes separated by a | <br/><br/>
											     Example: FR|GB|IE', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'currencies'     => array(
				'title'       => __( 'Currencies*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'multiselectcheckbox',
				'class'       => 'ob_currencies required',
				'css'         => 'width: 450px; height: 110px',
				'description' => __( 'Note: The payment method will be displayed at checkout only for the selected currencies.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'GBP' => 'GBP',
					'EUR' => 'EUR',
				),
				'default' => array(
					'GBP',
					'EUR',
				),
			),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_frontend_payment_method_options() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_method_availability() {
		$countries = $this->countries ? explode( "|", $this->countries ) : array();
		return array(
			'GBP' => $countries,
			'EUR' => $countries
		);
	}

	/**
	 * @inheritdoc
	 */
	public function payment_fields() {
		parent::payment_fields();
		$imgPath = Plugin::get_url('') . '/assets/frontend/img/Bank_Payment.png';
		?>
			<img class="openbanking-img-allign" src="<?= $imgPath ?>">
		<?php
	}
}
