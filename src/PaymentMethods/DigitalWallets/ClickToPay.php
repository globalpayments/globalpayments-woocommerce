<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets;

use Automattic\WooCommerce\Utilities\OrderUtil;
use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class ClickToPay extends AbstractDigitalWallet {
	public const PAYMENT_METHOD_ID = 'globalpayments_clicktopay';

	/**
	 * Refers to the merchant’s account for Click To Pay.
	 * @var string
	 */
	public $ctp_client_id;

	/**
	 * Indicates the display mode of Click To Pay.
	 *
	 * @var bool
	 */
	public $buttonless;

	/**
	 * Indicates the card brands the merchant accepts for Click To Pay (allowedCardNetworks).
	 *
	 * @var
	 */
	public $cc_types;

	/**
	 * Indicates whether Canadian Visa debit cards are accepted.
	 *
	 * @var bool
	 */
	public $canadian_debit;

	/**
	 * Indicates whether the Global Payments footer is displayed during Click To Pay.
	 *
	 * @var bool
	 */
	public $wrapper;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->default_title      = __( 'Pay with Click To Pay', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Click To Pay', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Click To Pay via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function get_payment_method_form_fields() {
		return array(
			'ctp_client_id'          => array(
				'title'       => __( 'Click To Pay Client ID*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your Merchant ID provided by Click To Pay.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'buttonless'    => array(
				'title'       => __( 'Render Click To Pay natively', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Click To Pay will render natively within the payment form', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'canadian_debit'    => array(
				'title'       => __( 'Accept Canadian Visa debit cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Accept Canadian Visa debit cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'wrapper'    => array(
				'title'       => __( 'Display Global Payments footer', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'       => __( 'Display Global Payments footer within the payment form', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'cc_types'                    => array(
				'title'   => __( 'Accepted Cards*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'multiselectcheckbox',
				'class'   => 'accepted_cards required',
				'css'     => 'width: 450px; height: 110px',
				'options' => array(
					CardType::VISA       => 'Visa',
					CardType::MASTERCARD => 'MasterCard',
					CardType::AMEX       => 'AMEX',
					CardType::DISCOVER   => 'Discover',
				),
				'default' => array(
					CardType::VISA,
					CardType::MASTERCARD,
					CardType::AMEX,
					CardType::DISCOVER,
				),
			),
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
	 * @inheritdoc
	 */
	public function add_hooks() {
		parent::add_hooks();

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_billing_data_from_wallet' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_admin_order_shipping_data_from_wallet' ) );
		add_action( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'woocommerce_settings_set_payment_action' ) );
	}

	/**
	 * @inheritdoc
	 */
	public function enqueue_payment_scripts() {
		AbstractGateway::hosted_fields_script();
		$this->gateway->helper_script();

		wp_enqueue_style(
			'globalpayments-clicktopay',
			Plugin::get_url( '/assets/frontend/css/globalpayments-clicktopay.css' ),
			array(),
			Plugin::get_version()
		);

		wp_enqueue_script(
			'globalpayments-wc-clicktopay',
			Plugin::get_url( '/assets/frontend/js/globalpayments-clicktopay.js' ),
			array(
				'wc-checkout',
				'globalpayments-secure-payment-fields-lib',
				'globalpayments-helper',
				'wp-i18n' // include 'wp-i18n' for translation
			),
			Plugin::get_version(),
			true
		);

		// set script translation, this will look in plugin languages directory and look for .json translation file
		wp_set_script_translations('globalpayments-wc-clicktopay', 'globalpayments-gateway-provider-for-woocommerce', WP_PLUGIN_DIR . '/'. basename( dirname( __FILE__ , 4 ) ) . '/languages');

		wp_localize_script(
			'globalpayments-wc-clicktopay',
			'globalpayments_clicktopay_params',
			array(
				'id'                     => $this->id,
				'payment_method_options' => $this->get_frontend_payment_method_options(),
			)
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_frontend_payment_method_options() {
		try {
			return array_merge(
				$this->gateway->get_frontend_gateway_options(),
				array(
					'apms' => array(
						'allowedCardNetworks' => $this->cc_types,
						'clickToPay'          => array(
							'buttonless'    => $this->buttonless,
							'canadianDebit' => $this->canadian_debit,
							'cardForm'      => false,
							'ctpClientId'   => $this->ctp_client_id,
							'wrapper'       => $this->wrapper,
						),
					)
				)
			);
		} catch ( \Exception $e ) {
			return array(
				'error'   => true,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function get_mobile_type() {
		return EncyptedMobileType::CLICK_TO_PAY;
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment_after_gateway_response( Transaction $gateway_response, $is_successful, \WC_Order $order ) {
		parent::process_payment_after_gateway_response( $gateway_response, $is_successful, $order );

		$meta = array(
			'billing_address'  => wp_json_encode( $gateway_response->payerDetails->billingAddress ),
			'shipping_address' => wp_json_encode( $gateway_response->payerDetails->shippingAddress ),
			'email'            => $gateway_response->payerDetails->email,
			'payer'            => $gateway_response->payerDetails->firstName . ' ' . $gateway_response->payerDetails->lastName,
		);

		foreach ( $meta as $key => $value ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( sprintf( '_%s_%s', $this->id, $key ), $value );
			} else {
				update_post_meta( $order->get_id(), sprintf( '_%s_%s', $this->id, $key ), $value );
			}
		}
	}

	/**
	 * Display CTP billing and email address from transaction response, in admin order details.
	 *
	 * @param $order
	 */
	public function display_admin_order_billing_data_from_wallet( $order ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		$dw_billing_address = json_decode( get_post_meta( $order->get_id(), '_' . $this->id . '_billing_address', true ) );
		$dw_email           = get_post_meta( $order->get_id(), '_' . $this->id . '_email', true );
		$dw_payer           = get_post_meta( $order->get_id(), '_' . $this->id . '_payer', true );
		?>

		<div class="address">
			<h3>
				<?php esc_html_e( 'Click To Pay Billing', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
			</h3>
			<p>
.				<?php echo esc_html( $dw_payer ) . '</br>'; ?>
				<?php echo esc_html( $dw_billing_address->streetAddress1 ) . ( ! empty( esc_html( $dw_billing_address->streetAddress2 ) ) ? ', ' . esc_html( $dw_billing_address->streetAddress2 ) : '' ) . '</br>'; ?>
				<?php echo ( ! empty( esc_html( $dw_billing_address->streetAddress2 ) ) ? esc_html( $dw_billing_address->streetAddress3 ) . '</br>' : '' ); ?>
				<?php echo esc_html( $dw_billing_address->city ) . ', ' . esc_html( $dw_billing_address->state ) . ' ' . esc_html( $dw_billing_address->postalCode ); ?>
			</p>
			<?php echo '<p><strong>' . esc_html__( 'Email address:', 'globalpayments-gateway-provider-for-woocommerce' )
			. ':</strong> <a href="'
			. esc_url( 'mailto:'
			. esc_html( $dw_email ) )
			. '">'
			. esc_html( $dw_email )
			. '</a></p>'; ?>
		</div>

		<?php
	}

	/**
	 * Display CTP shipping address from transaction response, in admin order details.
	 *
	 * @param $order
	 */
	public function display_admin_order_shipping_data_from_wallet( $order ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}
		$dw_shipping_address = json_decode( get_post_meta( $order->get_id(), '_' . $this->id . '_shipping_address', true ) );
		?>

		<div class="address">
			<h3>
				<?php esc_html_e( 'Click To Pay Shipping', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
			</h3>
			<p>
				<?php echo esc_html( $dw_shipping_address->streetAddress1 ) . ( ! empty( $dw_shipping_address->streetAddress2 ) ? ', ' . esc_html( $dw_shipping_address->streetAddress2 ) : '' ) . '</br>'; ?>
				<?php echo ( ! empty( $dw_shipping_address->streetAddress2 ) ? esc_html( $dw_shipping_address->streetAddress3 ) . '</br>' : '' ); ?>
				<?php echo esc_html( $dw_shipping_address->city ) . ', ' . esc_html( $dw_shipping_address->state ) . ' ' . esc_html( $dw_shipping_address->postalCode ); ?>
			</p>
		</div>

		<?php
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
}
