<?php
/**
 * Returns information about the package and handles init.
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class Plugin {
	/**
	 * Version.
	 *
	 * @var string
	 */
	const VERSION = '1.5.1';

	/**
	 * Init the package.
	 */
	public static function init() {
		load_plugin_textdomain( 'globalpayments-gateway-provider-for-woocommerce', false, self::get_path() . '/languages' );

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		//initialize gift related hooks for Heartland ajax requests
		if ( true === wp_doing_ajax() || ! empty( $_GET['wc-ajax'] ) ) {
			$heartlandSettings = get_option( 'woocommerce_globalpayments_heartland_settings' );
			// prevent checkout blocker when Heartland settings not setted loop
			if (
			    !empty( $heartlandSettings )  &&
                'yes' === $heartlandSettings['enabled'] &&
                'yes' === $heartlandSettings['allow_gift_cards']
            ) {
				new HeartlandGateway();
			}
		}

		add_filter( 'woocommerce_payment_gateways', array( self::class, 'add_gateways' ) );
		add_action( 'woocommerce_order_actions', array( Gateways\AbstractGateway::class, 'add_capture_order_action' ) );
		add_action( 'woocommerce_order_action_capture_credit_card_authorization', array(
			Gateways\AbstractGateway::class,
			'capture_credit_card_authorization'
		) );
	}

	/**
	 * Appends our payment gateways to WooCommerce's known list
	 *
	 * @param string[] $methods
	 *
	 * @return string[]
	 */
	public static function add_gateways( $methods ) {
		$gateways = array(
			Gateways\HeartlandGateway::class,
			Gateways\GeniusGateway::class,
			Gateways\TransitGateway::class,
			Gateways\GpApiGateway::class,
			Gateways\GooglePayGateway::class,
			Gateways\ApplePayGateway::class,
		);

		foreach ( $gateways as $gateway ) {
			$methods[] = $gateway;
		}

		return $methods;
	}

	/**
	 * Return the version of the package.
	 *
	 * @return string
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Return the path to the package.
	 *
	 * @return string
	 */
	public static function get_path() {
		return dirname( __DIR__ );
	}

	public static function get_url( $path ) {
		return plugins_url( $path, dirname( __FILE__ ) );
	}
}
