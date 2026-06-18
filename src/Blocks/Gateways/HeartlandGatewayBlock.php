<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

/**
 * HeartlandGateway Blocks integration.
 *
 * Extends AbstractGatewayBlock to provide WooCommerce Blocks checkout support
 * for the Heartland (Portico) payment gateway.
 *
 * @property HeartlandGateway $gateway
 */
class HeartlandGatewayBlock extends AbstractGatewayBlock {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name = HeartlandGateway::GATEWAY_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		$script_path       = 'assets/frontend/blocks/heartland.js';
		$script_asset_path = Plugin::get_path() . '/assets/frontend/blocks/heartland.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC()->version,
			);
		$script_url        = Plugin::get_url( '/' ) . $script_path;

		$this->gateway::hosted_fields_script();

		wp_register_script(
			'globalpayments-heartland-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_enqueue_style(
			'globalpayments-heartland-blocks',
			Plugin::get_url( '/assets/frontend/css/globalpayments-secure-payment-fields.css' ),
			array(),
			Plugin::VERSION
		);

		// Enqueue gift card styles if gift cards are enabled
		$allow_gift_cards = property_exists( $this->gateway, 'allow_gift_cards' ) ? $this->gateway->allow_gift_cards : false;
		if ( $allow_gift_cards ) {
			wp_enqueue_style(
				'heartland-gift-cards-blocks',
				Plugin::get_url( '/assets/frontend/css/heartland-gift-cards.css' ),
				array(),
				Plugin::VERSION
			);
		}

		return array( 'globalpayments-heartland-blocks' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		$allow_gift_cards =
			property_exists( $this->gateway, 'allow_gift_cards' ) ? $this->gateway->allow_gift_cards : false;

		$data = array(
			'secure_payment_fields' => $this->heartland_secure_payment_fields(),
			'title'                 => $this->gateway->get_title(),
			'supports'              => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'id'                    => $this->gateway->id,
			'gateway_options'       => $this->gateway->secure_payment_fields_config(),
			'field_styles'          => $this->gateway->secure_payment_fields_styles(),
			'helper_params'         => $this->gateway->get_helper_params(),
			'allow_card_saving'     => $this->gateway->allow_card_saving,
			'environment_indicator' => $this->gateway->environment_indicator(),
			'allow_gift_cards'      => $allow_gift_cards,
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'currency_symbol'       => html_entity_decode( get_woocommerce_currency_symbol() ),
		);

		// Add gift card nonce and applied cards data when gift cards are enabled
		if ( $allow_gift_cards ) {
			$data['gift_card_nonce'] = wp_create_nonce( 'heartland_gift_card_nonce' );

			// Include currently applied gift cards for frontend display
			if ( WC()->session ) {
				$applied_gift_cards = WC()->session->get( 'heartland_gift_card_applied' );
				if ( is_object( $applied_gift_cards ) && count( get_object_vars( $applied_gift_cards ) ) > 0 ) {
					$data['applied_gift_cards'] = array();
					foreach ( $applied_gift_cards as $gift_card ) {
						$data['applied_gift_cards'][] = array(
							'id'          => $gift_card->gift_card_id ?? '',
							'name'        => $gift_card->gift_card_name ?? '',
							'balance'     => $gift_card->temp_balance ?? 0,
							'used_amount' => $gift_card->used_amount ?? 0,
						);
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Configuration for the secure payment fields. Used on server- and
	 * client-side portions of the integration.
	 *
	 * @return array
	 */
	private function heartland_secure_payment_fields(): array {
		return array(
			'card-number-field' => array(
				'class'       => 'card-number',
				'label'       => esc_html__(
					'Credit Card Number *',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'placeholder' => esc_html__(
					'•••• •••• •••• ••••',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'messages'    => array(
					'validation' => esc_html__(
                        'Please enter a valid Credit Card Number',
                        'globalpayments-gateway-provider-for-woocommerce'
                    ),
				),
			),
			'card-expiry-field' => array(
				'class'       => 'card-expiration',
				'label'       => esc_html__(
					'Credit Card Expiration Date *',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'placeholder' => esc_html__(
					'MM / YYYY',
					'globalpayments-gateway-provider-for-woocommerce'
				),
				'messages'    => array(
					'validation' => esc_html__(
						'Please enter a valid Credit Card Expiration Date',
						'globalpayments-gateway-provider-for-woocommerce'
					),
				),
			),
			'card-cvc-field'    => array(
				'class'       => 'card-cvv',
				'label'       => esc_html__('Card Security Code *', 'globalpayments-gateway-provider-for-woocommerce' ),
				'placeholder' => esc_html__( '•••', 'globalpayments-gateway-provider-for-woocommerce' ),
				'messages'    => array(
					'validation' => esc_html__(
						'Please enter a valid Credit Card Security Code',
						'globalpayments-gateway-provider-for-woocommerce'
					),
				),
			),
		);
	}
}
