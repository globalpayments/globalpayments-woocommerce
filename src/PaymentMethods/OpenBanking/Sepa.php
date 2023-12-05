<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\OpenBanking;

use GlobalPayments\Api\Entities\Enums\BankPaymentType;

defined( 'ABSPATH' ) || exit;

class Sepa extends AbstractOpenBanking {
	public const PAYMENT_METHOD_ID = 'globalpayments_sepa';

	public $bank_payment_type = BankPaymentType::SEPA;
	public $default_title = 'Pay with Sepa';
	public $account_name;
	public $iban;
	public $countries;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	public function configure_method_settings() {
		$this->id                 = self::PAYMENT_METHOD_ID;
		$this->method_title       = __( 'GlobalPayments - Sepa', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to Sepa via Unified Payments Gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_payment_method_form_fields() {
		return array(
			'iban' => array(
				'title'       => __( 'IBAN', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Key field for bank transfers for Europe-to-Europe transfers. Only required if no bank details are stored on account.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'account_name' => array(
				'title'       => __( 'Account Name', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The name of the individual or business on the bank account. Only required if no bank details are stored on account.', 'globalpayments-gateway-provider-for-woocommerce' ),
			),
			'countries' => array(
				'title'       => __( 'Countries', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Allows you to input a COUNTRY or string of COUNTRIES to limit what is shown to the customer. Including a country overrides your default account configuration. <br/><br/>
									                     Format: List of ISO 3166-2 (two characters) codes separated by a | <br/><br/>
									                     Example: FR|GB|IE', 'globalpayments-gateway-provider-for-woocommerce' ),
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
			'EUR' => $countries
		);
	}
}
