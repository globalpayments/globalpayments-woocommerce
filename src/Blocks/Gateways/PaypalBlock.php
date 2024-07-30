<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods\Apm\Paypal;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

class PaypalBlock extends AbstractPaymentMethodType {
	protected Paypal $paymentMethod;

	protected $name = Paypal::PAYMENT_METHOD_ID;

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {
		$gateways            = WC()->payment_gateways->payment_gateways();
		$this->paymentMethod = $gateways[ $this->name ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return $this->paymentMethod->is_available();
	}
	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		$script_path       = 'assets/frontend/blocks/gateways.js';
		$script_asset_path = Plugin::get_url('/') . 'assets/frontend/blocks/gateways.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC()->version,
			);
		$script_url        = Plugin::get_url('/') . $script_path;

		wp_register_script(
			str_replace( '_', '-', $this->name ) . '-block',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return array(
			str_replace( '_', '-', $this->name ) . '-block'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		return array(
			'id'            => $this->paymentMethod->id,
			'title'         => $this->paymentMethod->get_title(),
			'helper_params' => $this->paymentMethod->gateway->get_helper_params(),
		);
	}
}
