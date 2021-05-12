<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Enums\Secure3dVersion;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined('ABSPATH') || exit;

class CheckEnrollmentRequest extends AbstractAuthenticationsRequest {
	const ENROLLED     = 'ENROLLED';
	const NOT_ENROLLED = 'NOT_ENROLLED';
	const NO_RESPONSE  = 'NO_RESPONSE';

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_CHECK_ENROLLMENT;
	}

	public function do_request() {
		$response    = [];
		$requestData = $this->data;

		try {
			$paymentMethod = new CreditCardData();
			$paymentMethod->token = $this->getToken( $requestData );

			$threeDSecureData = Secure3dService::checkEnrollment( $paymentMethod )
				->withAmount( $requestData->amount )
				->withCurrency( $requestData->currency )
				->execute();

			$response['enrolled']             = $threeDSecureData->enrolled ?? self::NOT_ENROLLED;
			$response['version']              = $threeDSecureData->getVersion();
			$response['serverTransactionId']  = $threeDSecureData->serverTransactionId;
			$response['sessionDataFieldName'] = $threeDSecureData->sessionDataFieldName;

			if ( self::ENROLLED !== $threeDSecureData->enrolled ) {
				wp_send_json( $response );
			}

			if ( Secure3dVersion::TWO === $threeDSecureData->getVersion() ) {
				$response['methodUrl']   = $threeDSecureData->issuerAcsUrl;
				$response['methodData']  = $threeDSecureData->payerAuthenticationRequest;
				$response['messageType'] = $threeDSecureData->messageType;

				wp_send_json($response);
			}

			if ( Secure3dVersion::ONE === $threeDSecureData->getVersion() ) {
				$response['TermUrl']                              = $threeDSecureData->challengeReturnUrl;
				$response['status']                               = $threeDSecureData->status;
				$response['challengeMandated']                    = $threeDSecureData->challengeMandated;
				$response['challenge']['requestUrl']              = $threeDSecureData->issuerAcsUrl;
				$response['challenge']['encodedChallengeRequest'] = $threeDSecureData->payerAuthenticationRequest;
				$response['challenge']['messageType']             = $threeDSecureData->messageType;
			}
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