<?php
require 'vendor/autoload.php';
require 'vendor/autoload_packages.php';

if ( ! isset( $_SERVER[ 'SERVER_NAME' ] ) ) {
  $_SERVER[ 'SERVER_NAME' ] = 'localhost';
}

if ( getenv( 'WP_MULTISITE' ) ) {
  define( 'WP_TESTS_MULTISITE', 1 );
}

$tests_dir = dirname( __FILE__ );
$plugin_dir = dirname( $tests_dir );
$wp_tests_dir = getenv( 'WP_TESTS_DIR' )
  ? getenv( 'WP_TESTS_DIR' )
  : '/tmp/wordpress-tests-lib';

if ( ! is_dir( $wp_tests_dir ) ) {
  exit( sprintf(
    'WP Tests Library not found! Be sure to run `%s`',
    "WP_TESTS_DIR='${wp_tests_dir}' ./tests/bin/install-wp-tests.sh wordpress_test \$WP_DB_USERNAME \$WP_DB_PASSWORD"
  ) );
}

// Load test functions
include_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () use ( $plugin_dir ) {
  include_once $plugin_dir . '/wp-content/plugins/woocommerce/woocommerce.php';
} );

// Start up the WP testing environment.
include_once $wp_tests_dir . '/includes/bootstrap.php';
