<?php
/**
 * Returns information about the package and handles init.
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\AffirmBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\ApplePayBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\ClickToPayBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\GooglePayBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\KlarnaBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\OpenBankingBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\PaypalBlock;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Blocks\Gateways\GpApiGatewayBlock;

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
	const VERSION = '1.14.9';

	/**
	 * Init the package.
	 */
	public static function init() {
		$pluginDirName = basename( dirname( __FILE__ , 2 ) );

		load_plugin_textdomain( 'globalpayments-gateway-provider-for-woocommerce', false, $pluginDirName . '/languages' );

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		//initialize gift related hooks for Heartland ajax requests
		if ( true === wp_doing_ajax() || ! empty( $_GET['wc-ajax'] ) ) {
			$heartlandSettings = get_option( 'woocommerce_' . HeartlandGateway::GATEWAY_ID . '_settings' );
			// prevent checkout blocker when Heartland settings not set loop
			if (
				! empty( $heartlandSettings ) &&
				isset( $heartlandSettings['enabled'] ) &&
				'yes' === $heartlandSettings['enabled'] &&
				isset( $heartlandSettings['allow_gift_cards'] ) &&
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
		add_action( 'woocommerce_blocks_loaded', array( self::class, 'add_block_gateways' ) );
	}

	/**
	 * Appends our payment gateways to WooCommerce's known list.
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
		$gateways = array_merge( $gateways, GpApiGateway::get_payment_methods() );

		foreach ( $gateways as $gateway ) {
			$methods[] = $gateway;
		}

		return $methods;
	}

	public static function add_block_gateways() {
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function (PaymentMethodRegistry $payment_method_registry) {
				$payment_method_registry->register( new GpApiGatewayBlock() );
				$payment_method_registry->register( new ApplePayBlock() );
				$payment_method_registry->register( new ClickToPayBlock() );
				$payment_method_registry->register( new GooglePayBlock() );
				$payment_method_registry->register( new AffirmBlock() );
				$payment_method_registry->register( new KlarnaBlock() );
				$payment_method_registry->register( new OpenBankingBlock() );
				$payment_method_registry->register( new PaypalBlock() );
			}
		);
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

	/**
	 * Return the active gateway of enforcing a single toggle.
	 *
	 * @return string
	 */
	public static function get_active_gateway() {
		$availableGateways = array(
			Gateways\HeartlandGateway::GATEWAY_ID,
			Gateways\GeniusGateway::GATEWAY_ID,
			Gateways\TransitGateway::GATEWAY_ID,
			Gateways\GpApiGateway::GATEWAY_ID,
		);
		foreach ( $availableGateways as $gateway ) {
			$gatewaySettings = get_option( 'woocommerce_' . $gateway . '_settings' );
			if ( ! empty( $gatewaySettings ) && isset( $gatewaySettings['enabled'] ) && 'yes' === $gatewaySettings['enabled'] ) {
				return $gateway;
			}
		}

		return;
	}
}
