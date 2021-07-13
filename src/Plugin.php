<?php
/**
 * Returns information about the package and handles init.
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftCardOrder;

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
	const VERSION = '1.0.2';

	/**
	 * Init the package.
	 */
	public static function init() {
		load_plugin_textdomain( 'globalpayments-gateway-provider-for-woocommerce', false, self::get_path() . '/languages' );

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		add_filter( 'woocommerce_payment_gateways', array( self::class, 'add_gateways' ) );
		add_action( 'woocommerce_order_actions', array( Gateways\AbstractGateway::class, 'addCaptureOrderAction' ) );
		add_action( 'woocommerce_order_action_capture_credit_card_authorization', array( Gateways\AbstractGateway::class, 'capture_credit_card_authorization' ) );

		add_filter( 'admin_enqueue_scripts', array( Gateways\AbstractGateway::class, 'admin_enqueue_scripts' ) );
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
