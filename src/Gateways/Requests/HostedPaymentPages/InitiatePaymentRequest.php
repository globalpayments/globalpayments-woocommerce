<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\HostedPaymentPages;

use GlobalPayments\Api\Builders\HPPBuilder;
use GlobalPayments\Api\Entities\{
	Address,
	PayerDetails,
	PhoneNumber
};
use GlobalPayments\Api\Entities\Enums\{
	CaptureMode,
	ChallengeRequestIndicator,
	Channel,
	ExemptStatus,
	HPPAllowedPaymentMethods,
	PaymentMethodUsageMode,
	PhoneNumberType
};
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\UkCountyValidator;

defined( 'ABSPATH' ) || exit;

class InitiatePaymentRequest extends AbstractRequest {

	/**
	 * Get the transaction type HPP
	 *
	 * @return string|AbstractGateway::TXN_TYPE_CREATE_HPP
	 */
	public function get_transaction_type(): string {
		return AbstractGateway::TXN_TYPE_CREATE_HPP;
	}

	/**
	 * Execute the payment request and return the response.
	 *
	 * @return PayByLinkResponse Containing the HPP URL
	 */
	public function do_request(): mixed {
		$requestData = $this->data;

		// Validate UK counties if needed
		$this->validate_uk_counties();

		$store_country_code = wc_get_base_location()['country'] ?? 'US';
		$ref_text           = get_bloginfo( 'name' ) ?: 'WooCommerce Store';
		$ref_text          .= ' Order #' . $this->order->get_id();

		$payer               = $this->create_payer_from_order();
		$hpp_payment_methods = [ HPPAllowedPaymentMethods::CARD ];

		$hpp_builder = HPPBuilder::create()
						->withName( $ref_text )
						->withDescription( 'Payment for Order #' . $this->order->get_order_number() )
						->withReference( $ref_text )
						->withAmount( $this->order->get_total() )
						->withPayer( $payer )
						->withCurrency( $this->order->get_currency() )
						->withOrderReference( $ref_text )
						->withNotifications(
							$requestData['globalpayments_hpp']['returnUrl'],
							$requestData['globalpayments_hpp']['statusUrl'],
							$requestData['globalpayments_hpp']['cancelUrl']
						)
						->withBillingAddress( $payer->billingAddress )
						->withShippingAddress( $payer->shippingAddress )
						->withAddressMatchIndicator( $payer->billingAddress == $payer->shippingAddress )
						->withAuthentication(
							ChallengeRequestIndicator::CHALLENGE_PREFERRED,
							ExemptStatus::LOW_VALUE,
							true
						);

		// Add digital wallets if enabled
		$enabled_wallets = $this->get_digital_wallets();
		if ( ! empty( $enabled_wallets ) ) {
			$hpp_builder->withDigitalWallets( $enabled_wallets );
		}

		// Add alternative payment methods
		$enabled_alternative_payments = $this->get_alternative_payment_methods();
		if ( ! empty( $enabled_alternative_payments ) ) {
			$hpp_payment_methods = array_merge( $hpp_payment_methods, $enabled_alternative_payments );
		}

		$hpp_builder->withTransactionConfig(
			Channel::CardNotPresent,
			$store_country_code,
			CaptureMode::AUTO,
			$hpp_payment_methods,
			PaymentMethodUsageMode::SINGLE
		);
		// Add shipping phone if available. 
		if(property_exists( $payer, "shippingPhone" ) && $payer->shippingPhone !== "" && $payer->shippingPhone !== null ){
			$hpp_builder->withShippingPhone( $payer->shippingPhone );
		};
		return $hpp_builder->execute();
	}

	/**
	 * Get the arguments
	 *
	 * @return array
	 */
	public function get_args(): array {
		return array();
	}

	/**
	 * Create payer from WooCommerce order.
	 *
	 * @return PayerDetails Class containing payer data
	 */
	protected function create_payer_from_order(): PayerDetails {
		$payer_country_info = CountryUtils::getCountryInfo( $this->order->get_billing_country() );

		$billing_includes_phone_number = ( "" !== $this->order->get_billing_phone() );
		$shipping_includes_phone_number = ( "" !== $this->order->get_shipping_phone() );

		$payer              = new PayerDetails();
		$payer->firstName   = $this->order->get_billing_first_name();
		$payer->lastName    = $this->order->get_billing_last_name();
		$payer->email       = $this->order->get_billing_email();
		if( $billing_includes_phone_number || $shipping_includes_phone_number ){
			$payer->mobilePhone = new PhoneNumber(
				$payer_country_info['phoneCode'][0],
				$this->order->get_billing_phone() !== "" ? 
				$this->order->get_billing_phone() : 
				$this->order->get_shipping_phone() ,
				PhoneNumberType::MOBILE
			);
		}
		$payer->status = 'NEW';
		$payer->language = strtoupper(substr( get_locale(), 0, 2 )) ?? "EN";

		// Set billing address
		$billing_address                    = new Address();
		$billing_address->streetAddress1    = $this->order->get_billing_address_1();
		$billing_address->streetAddress2    = $this->order->get_billing_address_2();
		$billing_address->city              = $this->order->get_billing_city();

		// Convert billing state/county to 3-digit code for British counties, 
		// WordPress/WooCommerce does not store these codes, like it does for US states.
		if ( $payer_country_info['alpha2'] === 'GB' ) {
			$billing_state_code     = UkCountyValidator::get_county_code( $this->order->get_billing_state() );
			$billing_address->state = $billing_state_code ?: '';
		} else {
			$billing_address->state = $this->order->get_billing_state();
		}

		$billing_address->postalCode   = $this->order->get_billing_postcode();
		$billing_address->countryCode  = $payer_country_info['alpha2'];
		$billing_address->country      = $payer_country_info['alpha2'];
		$payer->billingAddress         = $billing_address;

		// Set shipping address if available
		if ( $this->order->has_shipping_address() ) {
			$shipping_address                    = new Address();
			$shipping_address->streetAddress1    = $this->order->get_shipping_address_1();
			$shipping_address->streetAddress2    = $this->order->get_shipping_address_2();
			$shipping_address->city              = $this->order->get_shipping_city();

			// Convert shipping state/county to 3-digit code for British counties only
			$shipping_country_code = $this->order->get_shipping_country();
			if ( $shipping_country_code === 'GB' ) {
				$shipping_state_code      = UkCountyValidator::get_county_code( $this->order->get_shipping_state() );
				$shipping_address->state  = $shipping_state_code ?: '';
			} else {
				$shipping_address->state = $this->order->get_shipping_state();
			}

			$shipping_address->postalCode  = $this->order->get_shipping_postcode();
			$shipping_address->countryCode = $payer_country_info['alpha2'];
			$shipping_address->country     = $payer_country_info['alpha2'];
			$payer->shippingAddress        = $shipping_address;
			if( $billing_includes_phone_number || $shipping_includes_phone_number ){
				$payer->shippingPhone          = new PhoneNumber(
					$payer_country_info['phoneCode'][0],
					$this->order->get_shipping_phone() !== "" ? 
					$this->order->get_shipping_phone() : 
					$this->order->get_billing_phone(),
					PhoneNumberType::SHIPPING
				);
			}
		} else {
			$payer->shippingAddress = $billing_address;
			if( $billing_includes_phone_number || $shipping_includes_phone_number ){

				$payer->shippingPhone   = new PhoneNumber(
					$payer_country_info['phoneCode'][0],
					$this->order->get_billing_phone() !== "" ? 
					$this->order->get_billing_phone() : 
					$this->order->get_shipping_phone() ,
					PhoneNumberType::SHIPPING
				);
			}
		}

		return $payer;
	}

	/**
	 * Validate UK counties for billing and shipping addresses.
	 *
	 * @return void
	 * @throws \Exception If validation fails.
	 */
	protected function validate_uk_counties(): void {
		$billing_country  = $this->order->get_billing_country();
		$shipping_country = $this->order->get_shipping_country();

		// Only validate if billing address is UK
		if ( $billing_country === 'GB' ) {
			$billing_error = UkCountyValidator::validate_checkout_county(
				[
					'country' => $billing_country,
					'state'   => $this->order->get_billing_state(),
				],
				'billing'
			);

			if ( $billing_error !== null ) {
				throw new \Exception( $billing_error['message'] );
			}
		}

		// Only validate shipping if UK and has shipping address
		if ( $this->order->has_shipping_address() && $shipping_country === 'GB' ) {
			$shipping_error = UkCountyValidator::validate_checkout_county(
				[
					'country' => $shipping_country,
					'state'   => $this->order->get_shipping_state(),
				],
				'shipping'
			);

			if ( $shipping_error !== null ) {
				throw new \Exception( $shipping_error['message'] );
			}
		}
	}

	/**
	 * Get digital wallets configuration from admin settings.
	 *
	 * @return array containing enabled digital wallets
	 */
	protected function get_digital_wallets(): array {
		$enabled_wallets = [];

		if ( isset( $this->config['enable_gpay_hpp'] ) && 'yes' === $this->config['enable_gpay_hpp'] ) {
			$enabled_wallets[] = 'googlepay';
		}

		if ( isset( $this->config['enable_applepay_hpp'] ) && 'yes' === $this->config['enable_applepay_hpp'] ) {
			$enabled_wallets[] = 'applepay';
		}

		return $enabled_wallets;
	}

	/**
	 * Get alternative payment methods configuration from admin settings.
	 *
	 * @return array<HPPAllowedPaymentMethods> array of enabled alternative payment methods
	 */
	protected function get_alternative_payment_methods(): array {
		$enabled_alternative_payments = [];

		if ( isset( $this->config['enable_blik_hpp'] ) && 'yes' === $this->config['enable_blik_hpp'] ) {
			$enabled_alternative_payments[] = HPPAllowedPaymentMethods::BLIK;
		}

		if ( isset( $this->config['enable_open_banking_hpp'] ) && 'yes' === $this->config['enable_open_banking_hpp'] ) {
			$enabled_alternative_payments[] = HPPAllowedPaymentMethods::BANK_PAYMENT;
		}

		return $enabled_alternative_payments;
	}
}