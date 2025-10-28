<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;

class GpApiGatewayBlock extends AbstractGatewayBlock {
	public function __construct() {
		$this->name = GpApiGateway::GATEWAY_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data()
	{
		$allow_pl_apms = ( WC()->countries->get_base_country() === 'PL' && get_woocommerce_currency() === 'PLN' ) ? true : false;

		// Check if gateway is in HPP mode
		$is_hpp_mode = method_exists($this->gateway, 'is_hpp_mode') && $this->gateway->is_hpp_mode();

		$data = array(
			'secure_payment_fields' => $this->gateway->secure_payment_fields(),
			'title'                 => $this->gateway->get_title(),
			'supports'              => array_filter( $this->gateway->supports, [$this->gateway, 'supports'] ),
			'id'                    => $this->gateway->id,
			'gateway_options'       => $this->gateway->secure_payment_fields_config(),
			'field_styles'          => $this->gateway->secure_payment_fields_styles(),
			'helper_params'         => $this->gateway->get_helper_params(),
			'allow_card_saving'     => $this->gateway->allow_card_saving,
			'threedsecure'          => $this->gateway->supports( 'globalpayments_three_d_secure' ) ? $this->gateway->getThreedsecureFields() : null,
			'environment_indicator' => $this->gateway->environment_indicator(),
			'enable_blik'           => $allow_pl_apms ? $this->gateway->enable_blik : "no",
			'enable_bank_select'    => $allow_pl_apms ? $this->gateway->enable_bank_select : "no",
		);

		if ( $is_hpp_mode ) {
			$data['secure_payment_fields'] = array();
			$data['gateway_options'] = array();
			$data['field_styles'] = array();
			$data['threedsecure'] = null;
		}

		return $data;
	}

	/**
	 * Configuration for the secure payment fields. Used on server- and
	 * client-side portions of the integration.
	 *
	 * @return array
	 */
	public function secure_payment_fields() {
		return array(
			'payment-form' => array(
				'class'       => 'payment-form'
			)
		);
	}
}
