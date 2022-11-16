<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;
use WC_Order;

defined('ABSPATH') || exit;

abstract class AbstractAuthenticationsRequest extends AbstractRequest {
	const YES = 'YES';

	public function __construct( $gateway_id, WC_Order $order = null, array $config = array() ) {
		parent::__construct( $gateway_id, $order, $config );

		if ( $this->order instanceof WC_Order ) {
			$this->data->order                        = new \stdClass();
			$this->data->order->amount                = $this->order->get_total();
			$this->data->order->currency              = $this->order->get_currency();
			$this->data->order->billingAddress        = $this->get_wc_billing_address();
			$this->data->order->shippingAddress       = $this->get_wc_shipping_address();
			$this->data->order->addressMatchIndicator = $this->data->order->billingAddress === $this->data->order->shippingAddress;
			$this->data->order->customerEmail         = $this->order->get_billing_email();
		}
	}

	public function get_args() {
		return array();
	}

	protected function getToken( $requestData ) {
		if ( ! isset( $requestData->wcTokenId ) && ! isset( $requestData->tokenResponse ) ) {
			throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		if ( isset( $requestData->wcTokenId ) && 'new' !== $requestData->wcTokenId ) {
			$tokenResponse = \WC_Payment_Tokens::get( $requestData->wcTokenId );
			if ( empty( $tokenResponse ) ) {
				throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.', 'globalpayments-gateway-provider-for-woocommerce' ) );
			}

			return $tokenResponse->get_token();
		}

		$tokenResponse = json_decode( $requestData->tokenResponse );
		if ( empty( $tokenResponse->paymentReference ) ) {
			throw new \Exception( __( 'Not enough data to perform 3DS. Unable to retrieve token.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		return $tokenResponse->paymentReference;
	}

	/**
	 * Get (Billing) Address object from WC_Order.
	 *
	 * @return Address
	 */
	protected function get_wc_billing_address() {
		$billingAddress = new Address();
		$billingAddress->streetAddress1 = $this->order->get_billing_address_1();
		$billingAddress->streetAddress2 = $this->order->get_billing_address_2();
		$billingAddress->city           = $this->order->get_billing_city();
		$billingAddress->state          = $this->order->get_billing_state();
		$billingAddress->postalCode     = $this->order->get_billing_postcode();
		$billingAddress->country        = $this->order->get_billing_country();
		$billingAddress->countryCode    = CountryUtils::getNumericCodeByCountry( $billingAddress->country );

		return $billingAddress;
	}

	/**
	 * Get (Shipping) Address object from WC_Order.
	 *
	 * @return Address
	 */
	protected function get_wc_shipping_address() {
		$shippingAddress = new Address();
		$shippingAddress->streetAddress1 = $this->order->get_shipping_address_1();
		$shippingAddress->streetAddress2 = $this->order->get_shipping_address_2();
		$shippingAddress->city           = $this->order->get_shipping_city();
		$shippingAddress->state          = $this->order->get_shipping_state();
		$shippingAddress->postalCode     = $this->order->get_shipping_postcode();
		$shippingAddress->country        = $this->order->get_shipping_country();
		$shippingAddress->countryCode    = CountryUtils::getNumericCodeByCountry( $shippingAddress->country );

		return $shippingAddress;
	}
}
