<?php
/**
 * Plugin Name: GlobalPayments Gateway Provider for WooCommerce
 * Plugin URI: https://github.com/globalpayments/globalpayments-woocommerce
 * Description: This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.
 * Version: 1.16.2
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC tested up to: 9.0.2
 * Author: Global Payments
*/

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGateway;

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		$message = sprintf( __( 'Your PHP version is %s but GlobalPayments For WooCommerce requires version 8.0+.', 'globalpayments-gateway-provider-for-woocommerce' ), PHP_VERSION );
		echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
	} );

	return;
}

register_activation_hook( __FILE__, 'globalpayments_check_dependencies' );

function globalpayments_check_dependencies() {
	$requiredExtensions = [ 'curl', 'dom', 'openssl', 'json', 'zlib', 'intl', 'mbstring', 'xml' ];
	foreach ( $requiredExtensions as $ext ) {
		if ( ! extension_loaded( $ext ) ) {
			$notices   = get_option( 'globalpayments_plugin_deferred_admin_notices', array() );
			$notices[] = sprintf( __( 'The GlobalPayments WooCommerce plugin requires the %s extension.', 'globalpayments-gateway-provider-for-woocommerce' ), $ext );
			update_option( 'globalpayments_plugin_deferred_admin_notices', $notices );
		}
	}
}

add_action( 'admin_notices', 'globalpayments_plugin_admin_notices' );

function globalpayments_plugin_admin_notices() {
	if ( $notices = get_option( 'globalpayments_plugin_deferred_admin_notices' ) ) {
		foreach ( $notices as $notice ) {
			echo "<div class='notice notice-error'><p>$notice</p></div>";
		}
		delete_option( 'globalpayments_plugin_deferred_admin_notices' );
		deactivate_plugins( __FILE__ );
	}
}

register_deactivation_hook( __FILE__, 'globalpayments_plugin_deactivation' );

function globalpayments_plugin_deactivation() {
	delete_option( 'globalpayments_plugin_deferred_admin_notices' );
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
	if (
		empty( $hook_extra ) ||
		'plugin' !== ( $hook_extra['type'] ?? '' ) ||
		!isset( $hook_extra['plugins'] ) ||
		!is_array( $hook_extra['plugins'] ) ||
		!in_array( plugin_basename( __FILE__ ), $hook_extra['plugins'], true )
	) {
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
			if ( HeartlandGateway::GATEWAY_ID === $gateway_id ) {
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
	if (
		empty( $hook_extra ) ||
		'plugin' !== ( $hook_extra['type'] ?? '' ) ||
		!isset( $hook_extra['plugins'] ) ||
		!is_array( $hook_extra['plugins'] ) ||
		!in_array( plugin_basename( __FILE__ ), $hook_extra['plugins'], true )
	) {
		return;
	}
	if ( 'update' === $hook_extra[ 'action' ] || 'install' === $hook_extra[ 'action' ] ) {
		delete_option( 'woocommerce_globalpayments_version' );
		update_option( 'woocommerce_globalpayments_version', \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::VERSION );

		globalpayments_check_dependencies();
	}
}
add_action( 'upgrader_process_complete', 'globalpayments_update_plugin_version', 10, 2 );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
