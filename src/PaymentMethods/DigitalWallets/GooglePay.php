<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

defined( 'ABSPATH' ) || exit;

class GooglePay extends AbstractDigitalWallet {
	public const PAYMENT_METHOD_ID = 'globalpayments_googlepay';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $default_title = 'Pay with Google Pay';

	/**
	 * Supported credit card types
	 *
	 * @var array
	 */
	public $cc_types;

	/**
	 * Global Payments Merchant Id
	 *
	 * @var string
	 *
	 */
	public $global_payments_merchant_id;

	/**
	 * Google Merchant Id
	 *
	 * @var int
	 */
	public $google_merchant_id;

	/**
	 * Google Merchant Name
	 *
	 * @var string
	 */
	public $google_merchant_name;

	/**
	 * Google pay button color
	 *
	 * @var string
	 */
	public $button_color;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Google Pay', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Google Pay via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function get_payment_method_form_fields() {
		return array(
			'global_payments_merchant_id' => array(
				'title'             => __( 'Global Payments Client ID*', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Your Client ID provided by Global Payments.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'custom_attributes' => array( 'required' => 'required' ),
			),
			'google_merchant_id'          => array(
				'title'       => __( 'Google Merchant ID', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your Merchant ID provided by Google.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'google_merchant_name'        => array(
				'title'       => __( 'Google Merchant Display Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Displayed to the customer in the Google Pay dialog.', 'globalpayments-gateway-provider-for-woocommerce' ),
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
					CardType::JCB        => 'JCB',
				),
				'default' => array(
					CardType::VISA,
					CardType::MASTERCARD,
					CardType::AMEX,
					CardType::DISCOVER,
					CardType::JCB,
				),
			),
			'button_color'                => array(
				'title'   => __( 'Button Color', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'white',
				'options' => array(
					'white' => __( 'White', 'globalpayments-gateway-provider-for-woocommerce' ),
					'black' => __( 'Black', 'globalpayments-gateway-provider-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function enqueue_payment_scripts() {
		wp_enqueue_script(
			'globalpayments-googlepay',
			( 'https://pay.google.com/gp/p/js/pay.js' ),
			array(),
			WC()->version,
			true
		);
		$this->gateway->helper_script();

		wp_enqueue_script(
			'globalpayments-wc-googlepay',
			Plugin::get_url( '/assets/frontend/js/globalpayments-googlepay.js' ),
			array( 'wc-checkout', 'globalpayments-googlepay', 'globalpayments-helper' ),
			Plugin::get_version(),
			true
		);

		wp_localize_script(
			'globalpayments-wc-googlepay',
			'globalpayments_googlepay_params',
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
		return array(
			'env'                         => $this->gateway->is_production ? Environment::PRODUCTION : Environment::TEST,
			'google_merchant_id'          => $this->google_merchant_id,
			'google_merchant_name'        => $this->google_merchant_name,
			'global_payments_merchant_id' => $this->global_payments_merchant_id,
			'cc_types'                    => $this->cc_types,
			'button_color'                => $this->button_color,
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get_mobile_type() {
		return EncyptedMobileType::GOOGLE_PAY;
	}
}
