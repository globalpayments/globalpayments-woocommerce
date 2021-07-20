<?php
/**
 * Plugin Name: GlobalPayments WooCommerce
 * Plugin URI: https://github.com/globalpayments/globalpayments-woocommerce
 * Description: This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.
 * Version: 1.1.0
 * Requires PHP: 5.5.9
 * WC tested up to: 5.0.0
 * Author: Global Payments
*/

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '5.5.9', '<' ) ) {
	return;
}

/**
 * Autoload SDK.
 */
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
	include_once $autoloader;
	add_action( 'plugins_loaded', array( \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::class, 'init' ) );
}

function globalpayments_gpapi_upgrader_process_complete( WP_Upgrader $wp_upgrader, $hook_extra ) {
	if ( 'plugin' !== $hook_extra[ 'type' ] || plugin_basename( __FILE__ ) == $hook_extra[ 'plugins' ] ) {
		return;
	}
	if ( 'update' === $hook_extra[ 'action' ] || 'install' === $hook_extra[ 'action' ] ) {
		$current_plugin_version = get_option( 'woocommerce_globalpayments_version' );
		if ( ! empty( $current_plugin_version ) ) {
			if ( version_compare( \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::VERSION, $current_plugin_version, '>') ) {
				delete_option( 'woocommerce_globalpayments_version' );
				update_option( 'woocommerce_globalpayments_version', \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::VERSION );
			}
			return;
		}
		if ( empty( $settings['app_id'] ) && empty( $settings['app_key'] ) ) {
			return;
		}
		if ( ! wc_string_to_bool( $settings['is_production'] ) ) {
			$settings['sandbox_app_id']  = $settings['app_id'];
			$settings['sandbox_app_key'] = $settings['app_key'];
			$settings['app_id']          = '';
			$settings['app_key']         = '';
			update_option( 'woocommerce_globalpayments_gpapi_settings', $settings );
		}
		update_option( 'woocommerce_globalpayments_version', \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::VERSION );
	}
}
add_action( 'upgrader_process_complete', 'globalpayments_gpapi_upgrader_process_complete', 10, 2 );
