<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Enums\Secure3dVersion;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined('ABSPATH') || exit;

class CheckEnrollmentRequest extends AbstractAuthenticationsRequest {
	const NO_RESPONSE  = 'NO_RESPONSE';

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_CHECK_ENROLLMENT;
	}

	public function do_request() {
		$response    = [];
		$requestData = $this->data;

		try {
			$paymentMethod        = new CreditCardData();
			$paymentMethod->token = $this->getToken( $requestData );

			$threeDSecureData = Secure3dService::checkEnrollment( $paymentMethod )
			                                   ->withAmount( $requestData->order->amount )
			                                   ->withCurrency( $requestData->order->currency )
			                                   ->execute();

			$response['enrolled']             = $threeDSecureData->enrolled ?? Secure3dStatus::NOT_ENROLLED;
			$response['version']              = $threeDSecureData->getVersion();
			$response['status']               = $threeDSecureData->status;
			$response['liabilityShift']       = $threeDSecureData->liabilityShift;
			$response['serverTransactionId']  = $threeDSecureData->serverTransactionId;
			$response['sessionDataFieldName'] = $threeDSecureData->sessionDataFieldName;

			if ( Secure3dStatus::ENROLLED !== $threeDSecureData->enrolled ) {
				wp_send_json( $response );
			}

			if ( Secure3dVersion::TWO === $threeDSecureData->getVersion() ) {
				$response['methodUrl']   = $threeDSecureData->issuerAcsUrl;
				$response['methodData']  = $threeDSecureData->payerAuthenticationRequest;
				$response['messageType'] = $threeDSecureData->messageType;

				wp_send_json( $response );
			}

			if ( Secure3dVersion::ONE === $threeDSecureData->getVersion() ) {
				throw new \Exception( __( 'Please try again with another card.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
		}
		catch( ApiException $e ) {
			wc_get_logger()->error( $e->getMessage() );
			if ( '50022' == $e->responseCode ) {
				throw new \Exception( esc_html__( 'Please try again with another card.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}
			throw new \Exception( esc_html($e->getMessage()) );
		} catch (\Exception $e) {
			$response = [
				'error'    => true,
				'message'  => $e->getMessage(),
				'enrolled' =>  self::NO_RESPONSE,
			];
		}

		wp_send_json( $response );
	}
}