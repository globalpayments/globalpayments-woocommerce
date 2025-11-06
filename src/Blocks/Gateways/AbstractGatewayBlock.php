<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

abstract class AbstractGatewayBlock extends AbstractPaymentMethodType {
	protected AbstractGateway $gateway;

	protected $name;

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {
		$gateways      = WC()->payment_gateways->payment_gateways();
		$this->gateway = $gateways[ $this->name ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		$script_path = ( !empty( $this->gateway->payment_interface ) && $this->gateway->payment_interface === 'hpp' ) ?
			'assets/frontend/blocks/gateways_hpp.js' : 'assets/frontend/blocks/gateways.js';
		$script_asset_path = Plugin::get_url('/') . 'assets/frontend/blocks/gateways.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC()->version,
			);
		$script_url        = Plugin::get_url('/') . $script_path;

		$this->gateway::hosted_fields_script();

		wp_register_script(
			'globalpayments-secure-payment-fields-gateways-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		wp_enqueue_style(
			'globalpayments-secure-payment-fields-gateways-blocks',
			Plugin::get_url( '/assets/frontend/css/globalpayments-secure-payment-fields.css' ),
			array(),
			Plugin::VERSION
		);

		$handles = [ 'globalpayments-secure-payment-fields-gateways-blocks' ];

		if ( $this->gateway->supports( 'globalpayments_three_d_secure' ) ) {
			wp_register_script(
				'globalpayments-threedsecure-lib',
				Plugin::get_url( '/assets/frontend/js/globalpayments-3ds' )
				. ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' ) . '.js',
				array( 'globalpayments-secure-payment-fields-lib' ),
				Plugin::VERSION,
				true
			);

			array_push($handles, 'globalpayments-threedsecure-lib' );
		}

		return $handles;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		return array(
			'secure_payment_fields' => $this->gateway->secure_payment_fields(),
			'title'                 => $this->gateway->get_title(),
			'supports'              => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
			'id'                    => $this->gateway->id,
			'gateway_options'       => $this->gateway->secure_payment_fields_config(),
			'field_styles'          => $this->gateway->secure_payment_fields_styles(),
			'helper_params'         => $this->gateway->get_helper_params(),
			'allow_card_saving'     => $this->gateway->allow_card_saving,
			'threedsecure'          => $this->gateway->supports( 'globalpayments_three_d_secure' ) ? $this->gateway->getThreedsecureFields() : null,
			'environment_indicator' => $this->gateway->environment_indicator(),
		);
	}
}
