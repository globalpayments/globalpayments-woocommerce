<?php
/**
 * Plugin Name: GlobalPayments WooCommerce
 * Plugin URI: https://github.com/globalpayments/globalpayments-woocommerce
 * Description: This extension allows WooCommerce to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.
 * Version: 1.0.2
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
