<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined( 'ABSPATH' ) || exit;

class GetAccessTokenRequest extends AbstractRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_GET_ACCESS_TOKEN;
	}

	public function get_args() {
        if( isset($_POST['_wpnonce']) ) {
            return array();
        }

        $permissions = array(
            'PMT_POST_Create_Single',
        );

        // Add installments permissions if installments is enabled
        $gateway_settings = get_option( 'woocommerce_globalpayments_gpapi_settings', array() );
        $store_country = get_option( 'woocommerce_default_country', '' );
        $country_code = explode( ':', $store_country )[0];
        $default_currency = get_option( 'woocommerce_currency', '' );

        if (
            ! empty( $gateway_settings['enable_installments'] )
            && $gateway_settings['enable_installments'] === 'yes'
            && $country_code === 'MX'
            && $default_currency === 'MXN'
        ) {
            $permissions = array_merge(
                $permissions,
                array( 'INS_POST_Query', 'BIN_GET_Details', 'PMT_POST_Create' )
            );
        }

        return array(
            RequestArg::PERMISSIONS => $permissions,
        );
	}
}
