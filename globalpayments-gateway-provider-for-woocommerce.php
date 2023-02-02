<?php
/**
 * Plugin Name: GlobalPayments WooCommerce
 * Plugin URI: https://github.com/globalpayments/globalpayments-woocommerce
 * Description: This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.
 * Version: 1.5.3
 * Requires PHP: 7.1
 * WC tested up to: 7.2.2
 * Author: Global Payments
*/

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.1', '<' ) ) {
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

function globalpayments_update_v110_v111( WP_Upgrader $wp_upgrader, $hook_extra ) {
	if ( empty( $hook_extra )  || 'plugin' !== $hook_extra[ 'type' ] || ! in_array( plugin_basename( __FILE__ ), $hook_extra[ 'plugins' ] ) ) {
		return;
	}
	if ( 'update' === $hook_extra[ 'action' ] || 'install' === $hook_extra[ 'action' ] ) {
		$current_plugin_version = get_option( 'woocommerce_globalpayments_version' );
		if ( ! empty( $current_plugin_version ) ) {
			return;
		}
		$globalpayments_keys = [
			'globalpayments_gpapi'     => [
				'app_id',
				'app_key',
			],
			'globalpayments_heartland' => [
				'public_key',
				'secret_key',
			],
			'globalpayments_genius'    => [
				'merchant_name',
				'merchant_site_id',
				'merchant_key',
				'web_api_key',
			],
			'globalpayments_transit'   => [
				'merchant_id',
				'user_id',
				'password',
				'device_id',
				'tsep_device_id',
				'transaction_key',
			],
		];
		foreach ( $globalpayments_keys as $gateway_id => $gateway_keys ) {
			$settings = get_option( 'woocommerce_' . $gateway_id . '_settings' );
			if ( 'globalpayments_heartland' === $gateway_id ) {
				$settings['is_production'] = ( isset( $settings['public_key'] ) && false !== strpos( $settings['public_key'], 'pkapi_prod_' ) ) ? 'yes' : 'no';
			}
			// General rule: if the gateway is not set to "Live Mode", move the credentials in sandbox keys.
			if ( ! isset( $settings['is_production'] ) || ! wc_string_to_bool( $settings['is_production'] ) ) {
				foreach ( $gateway_keys as $gateway_key ) {
					if ( ! empty( $settings[$gateway_key] ) ) {
						$settings['sandbox_' . $gateway_key] = $settings[$gateway_key];
						$settings[$gateway_key] = '';
					}
				}
			}
			update_option( 'woocommerce_' . $gateway_id . '_settings', $settings );
		}
	}
}
add_action( 'upgrader_process_complete', 'globalpayments_update_v110_v111', 9, 2 );

function globalpayments_update_plugin_version( WP_Upgrader $wp_upgrader, $hook_extra ) {
	if ( empty( $hook_extra ) || 'plugin' !== $hook_extra[ 'type' ] || ! in_array( plugin_basename( __FILE__ ), $hook_extra[ 'plugins' ] ) ) {
		return;
	}
	if ( 'update' === $hook_extra[ 'action' ] || 'install' === $hook_extra[ 'action' ] ) {
		delete_option( 'woocommerce_globalpayments_version' );
		update_option( 'woocommerce_globalpayments_version', \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::VERSION );
	}
}
add_action( 'upgrader_process_complete', 'globalpayments_update_plugin_version', 10, 2 );