<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;

defined('ABSPATH') || exit;

abstract class AbstractAuthenticationsRequest extends AbstractRequest {
	public function get_args() {
		return array();
	}

	protected function getToken( $requestData ) {
		if ( ! isset( $requestData->wcTokenId ) && ! isset( $requestData->tokenResponse ) ) {
			throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.' ) );
		}

		if ( isset( $requestData->wcTokenId ) && 'new' !== $requestData->wcTokenId ) {
			$tokenResponse = \WC_Payment_Tokens::get( $requestData->wcTokenId );
			if ( empty( $tokenResponse ) ) {
				throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.' ) );
			}

			return $tokenResponse->get_token();
		}

		$tokenResponse = json_decode( $requestData->tokenResponse );
		if ( empty( $tokenResponse->paymentReference ) ) {
			throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.' ) );
		}

		return $tokenResponse->paymentReference;
	}
}