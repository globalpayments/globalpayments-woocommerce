<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class GetAccessTokenRequest extends AbstractRequest {
	/**
	 * Get request transaction type.
	 *
	 * @return string
	 */
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_GET_ACCESS_TOKEN;
	}

	/**
	 * Build request args.
	 *
	 * @return array
	 */
	public function get_args() {
		if ( isset( $_POST['_wpnonce'] ) ) {
			return array();
		}

		$permissions = array(
			'PMT_POST_Create_Single',
		);

		// Add installments permissions if installments is enabled.
		$gateway_settings = get_option( 'woocommerce_globalpayments_gpapi_settings', array() );
		$store_country    = get_option( 'woocommerce_default_country', '' );
		$country_code     = explode( ':', $store_country )[0];
		$default_currency = get_option( 'woocommerce_currency', '' );

		if (
			! empty( $gateway_settings['enable_installments'] )
			&& 'yes' === $gateway_settings['enable_installments']
			&& 'MX' === $country_code
			&& 'MXN' === $default_currency
		) {
			$permissions = array_merge(
				$permissions,
				array( 'INS_POST_Query', 'BIN_GET_Details', 'PMT_POST_Create' )
			);
		}

		// Add DCC permission if DCC is enabled and payment interface is HPP.
		if (
			! empty( $gateway_settings['enable_dcc'] )
			&& 'yes' === $gateway_settings['enable_dcc']
			&& ! empty( $gateway_settings['payment_interface'] )
			&& 'hpp' === $gateway_settings['payment_interface']
		) {
			$permissions = array_merge( $permissions, array( 'CCS_POST_DCC', 'PMT_POST_Create' ) );
		}

		// Prevent duplicate permission entries when multiple feature flags append the same scope.
		$permissions = array_values( array_unique( $permissions ) );

        return array(
            RequestArg::PERMISSIONS => $permissions,
            RequestArg::RESTRICTED_TOKEN => true
        );
	}
}
