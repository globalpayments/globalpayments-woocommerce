<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use GlobalPayments\Api\Entities\Enums\BNPLType;

defined( 'ABSPATH' ) || exit;

class Klarna extends AbstractBuyNowPayLater {
	public const PAYMENT_METHOD_ID = 'globalpayments_klarna';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $payment_method_BNPL_provider = BNPLType::KLARNA;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $default_title = 'Pay with Klarna';

	/**
	 * @inheritDoc
	 */
	public function configure_method_settings() {
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Klarna', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Klarna via Unified Payments Gateway',
			'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * @inheritDoc
	 */
	public function get_payment_method_form_fields() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_method_availability() {
		return array(
			'USD' => array( 'US' ),
			'CAD' => array( 'CA' ),
			'GBP' => array( 'GB' ),
			'AUD' => array( 'AU' ),
			'NZD' => array( 'NZ' ),
			'EUR' => array( 'AT', 'BE', 'DE', 'ES', 'FI', 'FR', 'IT', 'NL' ),
			'CHF' => array( 'CH' ),
			'DKK' => array( 'DK' ),
			'NOK' => array( 'NO' ),
			'PLN' => array( 'PL' ),
			'SEK' => array( 'SE' ),
		);
	}
}
