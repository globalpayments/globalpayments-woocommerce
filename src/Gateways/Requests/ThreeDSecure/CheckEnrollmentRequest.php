<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Enums\Secure3dVersion;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;

defined('ABSPATH') || exit;

class CheckEnrollmentRequest extends AbstractRequest {
	const ENROLLED     = 'ENROLLED';
	const NOT_ENROLLED = 'NOT_ENROLLED';

	const NO_RESPONSE  = 'NO_RESPONSE';

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_CHECK_ENROLLMENT;
	}

	public function get_args() {
		return array();
	}

	public function do_request() {
		$response    = [];
		$requestData = $this->data;
		try {
			if ( isset( $requestData->wcTokenId ) && 'new' !== $requestData->wcTokenId ) {
				$tokenResponse = \WC_Payment_Tokens::get( $requestData->wcTokenId );
				$token = $tokenResponse->get_token();
			} else {
				$tokenResponse = json_decode( $requestData->tokenResponse );
				$token = $tokenResponse->paymentReference;
			}

			$paymentMethod = new CreditCardData();
			$paymentMethod->token = $token;

			$threeDSecureData = Secure3dService::checkEnrollment($paymentMethod)
				->withAmount($requestData->amount)
				->withCurrency($requestData->currency)
				->execute();

			$response["enrolled"] = $threeDSecureData->enrolled ?? self::NOT_ENROLLED;
			$response['version'] = $threeDSecureData->getVersion();
			$response["serverTransactionId"] = $threeDSecureData->serverTransactionId ?? '';
			if ( self::ENROLLED !== $threeDSecureData->enrolled ) {
				wp_send_json( $response );
			}

			if ( Secure3dVersion::TWO === $threeDSecureData->getVersion() ) {
				$response["methodUrl"] = $threeDSecureData->issuerAcsUrl ?? '';
				$response['methodData'] = $threeDSecureData->payerAuthenticationRequest ?? '';

				wp_send_json($response);
			}

			if ( Secure3dVersion::ONE === $threeDSecureData->getVersion() ) {
				$response['TermUrl']                              = $threeDSecureData->challengeReturnUrl;
				$response["status"]                               = $threeDSecureData->status;
				$response["challengeMandated"]                    = $threeDSecureData->challengeMandated;
				$response["challenge"]["requestUrl"]              = $threeDSecureData->issuerAcsUrl;
				$response["challenge"]["encodedChallengeRequest"] = $threeDSecureData->payerAuthenticationRequest;
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