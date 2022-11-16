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

defined( 'ABSPATH' ) || exit;

class InitiateAuthenticationRequest extends AbstractAuthenticationsRequest {
	/**
	 * Country codes to send the state for
	 * CA: "124", US: "840"
	 *
	 * @var array
	 */
	private $country_codes = [ 124, 840 ];

	public function get_transaction_type() {
		return AbstractGateway::TXN_TYPE_INITIATE_AUTHENTICATION;
	}

	public function do_request() {
		$response    = [];
		$requestData = $this->data;

		try {
			$paymentMethod        = new CreditCardData();
			$paymentMethod->token = $this->getToken( $requestData );

			$threeDSecureData                      = new ThreeDSecure();
			$threeDSecureData->serverTransactionId = $requestData->versionCheckData->serverTransactionId;
			$methodUrlCompletion                   = ( $requestData->versionCheckData->methodData && $requestData->versionCheckData->methodUrl ) ?
				MethodUrlCompletion::YES : MethodUrlCompletion::NO;

			$threeDSecureData = Secure3dService::initiateAuthentication( $paymentMethod, $threeDSecureData )
				->withAmount( $requestData->order->amount )
				->withCurrency( $requestData->order->currency )
				->withOrderCreateDate( date( 'Y-m-d H:i:s' ) )
				->withAddress( $this->map_address( $requestData->order->billingAddress ), AddressType::BILLING )
				->withAddress( $this->map_address( $requestData->order->shippingAddress ), AddressType::SHIPPING )
				->withAddressMatchIndicator( $requestData->order->addressMatchIndicator )
				->withCustomerEmail( $requestData->order->customerEmail )
				->withAuthenticationSource( $requestData->authenticationSource )
				->withAuthenticationRequestType( $requestData->authenticationRequestType )
				->withMessageCategory( $requestData->messageCategory )
				->withChallengeRequestIndicator( $requestData->challengeRequestIndicator )
				->withBrowserData( $this->get_browser_data( $requestData ) )
				->withMethodUrlCompletion( $methodUrlCompletion )
				->execute();

			$response['liabilityShift'] = $threeDSecureData->liabilityShift;
			// frictionless flow
			if ( $threeDSecureData->status !== 'CHALLENGE_REQUIRED' ) {
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
		} catch ( \Exception $e ) {
			$response = [
				'error'   => true,
				'message' => $e->getMessage(),
			];
		}

		wp_send_json( $response );
	}

	private function get_browser_data( $requestData ) {
		$browserData                     = new BrowserData();
		$browserData->acceptHeader       = isset( $_SERVER['HTTP_ACCEPT'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		$browserData->colorDepth         = $requestData->browserData->colorDepth;
		$browserData->ipAddress          = isset( $_SERVER['REMOTE_ADDR'] ) ? wc_clean( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$browserData->javaEnabled        = $requestData->browserData->javaEnabled ?? false;
		$browserData->javaScriptEnabled  = $requestData->browserData->javascriptEnabled;
		$browserData->language           = $requestData->browserData->language;
		$browserData->screenHeight       = $requestData->browserData->screenHeight;
		$browserData->screenWidth        = $requestData->browserData->screenWidth;
		$browserData->challengWindowSize = $requestData->challengeWindow->windowSize;
		$browserData->timeZone           = $requestData->browserData->timezoneOffset;
		$browserData->userAgent          = $requestData->browserData->userAgent;

		return $browserData;
	}

	private function map_address( $addressData ) {
		if ( $addressData instanceof Address ) {
			return $addressData;
		}

		$address              = new Address();
		$address->countryCode = CountryUtils::getNumericCodeByCountry( $addressData->country );

		foreach ( $addressData as $key => $value ) {
			if ( property_exists( $address, $key ) && ! empty( $value ) ) {
				if ( 'state' == $key && ! in_array( $address->countryCode, $this->country_codes ) ) {
					continue;
				}
				$address->{$key} = $value;
			}
		};

		return $address;
	}
}
