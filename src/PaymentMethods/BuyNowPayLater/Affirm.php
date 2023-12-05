<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use GlobalPayments\Api\Entities\Enums\BNPLType;

defined( 'ABSPATH' ) || exit;

class Affirm extends AbstractBuyNowPayLater {
	public const PAYMENT_METHOD_ID = 'globalpayments_affirm';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $payment_method_BNPL_provider = BNPLType::AFFIRM;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public $default_title = 'Pay with Affirm';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Affirm', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Affirm via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @var string
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
		);
	}

	/**
	 * @inheritDoc
	 */
	public function is_shipping_required() {
		return true;
	}
}
