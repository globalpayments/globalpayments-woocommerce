<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\BrowserData;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\MethodUrlCompletion;
use GlobalPayments\Api\Entities\ThreeDSecure;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

defined('ABSPATH') || exit;

class InitiateAuthenticationRequest extends AbstractAuthenticationsRequest {
	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_INITIATE_AUTHENTICATION;
	}

	public function do_request() {
		$response = [];
		$requestData  = $this->data;

		try {
			$paymentMethod = new CreditCardData();
			$paymentMethod->token = $this->getToken( $requestData );

			$threeDSecureData = new ThreeDSecure();
			$threeDSecureData->serverTransactionId = $requestData->versionCheckData->serverTransactionId;
			$methodUrlCompletion = ( $requestData->versionCheckData->methodData && $requestData->versionCheckData->methodUrl ) ?
				MethodUrlCompletion::YES : MethodUrlCompletion::NO;

			$threeDSecureData = Secure3dService::initiateAuthentication( $paymentMethod, $threeDSecureData )
				->withAmount( $requestData->order->amount )
				->withCurrency( $requestData->order->currency )
				->withOrderCreateDate( date( 'Y-m-d H:i:s' ) )
				->withAddress( $this->mapAddress( $requestData->order->billingAddress ), AddressType::BILLING )
				->withAddress( $this->mapAddress( $requestData->order->shippingAddress ), AddressType::SHIPPING )
				->withCustomerEmail( $requestData->order->customerEmail )
				->withAuthenticationSource( $requestData->authenticationSource )
				->withAuthenticationRequestType( $requestData->authenticationRequestType )
				->withMessageCategory( $requestData->messageCategory )
				->withChallengeRequestIndicator( $requestData->challengeRequestIndicator )
				->withBrowserData( $this->getBrowserData( $requestData ) )
				->withMethodUrlCompletion( $methodUrlCompletion )
				->execute();

			// frictionless flow
			if ($threeDSecureData->status !== 'CHALLENGE_REQUIRED') {
				$response['result']              = $threeDSecureData->status;
				$response['authenticationValue'] = $threeDSecureData->authenticationValue;
				$response['serverTransactionId'] = $threeDSecureData->serverTransactionId;
				$response['messageVersion']      = $threeDSecureData->messageVersion;
				$response['eci']                 = $threeDSecureData->eci;

			} else { //challenge flow
				$response['status']                               = $threeDSecureData->status;
				$response['challengeMandated']                    = $threeDSecureData->challengeMandated;
				$response['challenge']['requestUrl']              = $threeDSecureData->issuerAcsUrl;
				$response['challenge']['encodedChallengeRequest'] = $threeDSecureData->payerAuthenticationRequest;
				$response['challenge']['messageType']             = $threeDSecureData->messageType;
			}
		} catch (\Exception $e) {
			$response = [
				'error'   => true,
				'message' => $e->getMessage(),
			];
		}

		wp_send_json( $response );
	}

	private function getBrowserData( $requestData ) {
		$browserData = new BrowserData();
		$browserData->acceptHeader = isset( $_SERVER['HTTP_ACCEPT'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		$browserData->colorDepth = $requestData->browserData->colorDepth;
		$browserData->ipAddress = isset( $_SERVER['REMOTE_ADDR'] ) ? wc_clean( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$browserData->javaEnabled = $requestData->browserData->javaEnabled ?? false;
		$browserData->javaEnabled = true;
		$browserData->javaScriptEnabled = $requestData->browserData->javascriptEnabled;
		$browserData->language = $requestData->browserData->language;
		$browserData->screenHeight = $requestData->browserData->screenHeight;
		$browserData->screenWidth = $requestData->browserData->screenWidth;
		$browserData->challengWindowSize = $requestData->challengeWindow->windowSize;
		$browserData->timeZone = 0;
		$browserData->userAgent = $requestData->browserData->userAgent;

		return $browserData;
	}

	private function mapAddress( $addressData ) {
		$address = new Address();
		foreach ( $addressData as $key => $value ) {
			if ( property_exists( $address, $key ) ) {
				$address->{$key} = $value;
			}
		};

		$address->countryCode = CountryUtils::getCountryCodeByCountry( $addressData->country );

		return $address;
	}
}