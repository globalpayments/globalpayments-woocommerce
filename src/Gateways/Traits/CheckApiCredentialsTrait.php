<?php
/**
 * WC GlobalPayments Admin View Transaction Status Trait
 */

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;

defined( 'ABSPATH' ) || exit;

trait CheckApiCredentialsTrait {
	public function check_api_credentials( ) {
		wp_localize_script(
			'globalpayments-admin',
			'globalpayments_admin_credentials_params',
			array(
				'_wpnonce'                  => wp_create_nonce( 'woocommerce-globalpayments-check_api_credentials' ),
				'check_api_credentials_url' => WC()->api_request_url( 'globalpayments_check_api_credentials_handler' ),
			)
		);
	}

	public function check_api_credentials_handler() {
		try {
			$nonce_value = wc_get_var( $_REQUEST['woocommerce-globalpayments-check_api_credentials'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.
			if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-globalpayments-check_api_credentials' ) ) {
				throw new \Exception( __( 'Invalid check api credentials request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			$app_id = wc_get_var( $_REQUEST['app_id'] );
			$app_key = wc_get_var( $_REQUEST['app_key'] );
			$environment_value = (bool) wc_get_var( $_REQUEST['environment'] );
			$environment = Environment::TEST;

			if ( ! $app_id || ! $app_key ) {
				throw new \Exception( __( 'App Id or App Key fields are invalid', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			if ($environment_value) {
				$environment = Environment::PRODUCTION;
			}

			$ajaxData['appId'] = $app_id;
			$ajaxData['appKey'] = $app_key;
			$ajaxData['environment'] = $environment;

			$this->gateway_provider = GatewayProvider::GP_API;
			$request = $this->prepare_request( GpApiGateway::TXN_TYPE_GET_ACCESS_TOKEN, null, $ajaxData );
			$gatewayResponse = $this->submit_request( $request );
			if ( ! empty($gatewayResponse->token )) {
				$response['error'] = false;
				$response['message'] = __( 'Your credentials were successfully confirmed!', 'globalpayments-gateway-provider-for-woocommerce' );
                $response['accounts'] = $gatewayResponse->accounts;
			} else {
				$response['error'] = true;
				$response['message'] = __( 'Unable to perform request. Invalid data.', 'globalpayments-gateway-provider-for-woocommerce' );
				unset( $response['error'] );
			}

			wp_send_json($response);
		} catch ( \Exception $e ) {
			wp_send_json( [
				'error'   => true,
				'message' => __( 'Unable to perform request. Invalid data.', 'globalpayments-gateway-provider-for-woocommerce' ) . ' '. $e->getMessage(),
			] );
		}
	}
}
