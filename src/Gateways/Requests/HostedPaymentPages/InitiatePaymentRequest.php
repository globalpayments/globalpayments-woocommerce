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
	PhoneNumberType,
	InstallmentsFundingMode
};
use GlobalPayments\Api\Utils\{
	CountryUtils,
	StringUtils
	};
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Services\InstallmentsService;

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

		$store_country_code = wc_get_base_location()['country'] ?? 'US';
		$ref_text           = get_bloginfo( 'name' ) ?: 'WooCommerce Store';
		$ref_text           = html_entity_decode( $ref_text, ENT_QUOTES, 'UTF-8' );
		//Check for non ASCII characters in the site title
		if( preg_match( '/[^\x00-\x7F]/', $ref_text ) ){
			$ref_text = $this->convertToASCII( $ref_text );
		};

		$orderNumString = ' Order #' . $this->order->get_id();

		// Some HPP-gateway fields don't accept strings longer than 50 characters,
		// so this avoids exceeding that limit in cases where the store name is especially
		// long and keeps the important order-number info intact.
		$ref_text = substr_replace(
			$ref_text,
			$orderNumString,
			50 - ( strlen( $orderNumString ) )
		);		
		
		$payer               = $this->create_payer_from_order();
		$hpp_payment_methods = [ HPPAllowedPaymentMethods::CARD ];

		$hpp_builder = HPPBuilder::create()
						->withName( $ref_text )
						->withDescription( 'Payment for Order #' . $this->order->get_order_number() )
						->withReference( $ref_text )
						->withAmount( StringUtils::toNumeric( $this->order->get_total() , $this->order->get_currency() ) )
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

		// Map admin payment action to HPP capture mode.
		$payment_action = $this->config['payment_action'] ?? null;
		if ( null === $payment_action && is_array( $requestData ) ) {
			$payment_action = $requestData['payment_action'] ?? null;
		}
		if ( null === $payment_action ) {
			$payment_action = AbstractGateway::TXN_TYPE_SALE;
		}

		$allowed_actions = array(
			AbstractGateway::TXN_TYPE_AUTHORIZE,
			AbstractGateway::TXN_TYPE_SALE,
		);
		if ( ! in_array( $payment_action, $allowed_actions, true ) ) {
			$payment_action = AbstractGateway::TXN_TYPE_SALE;
		}

		$capture_mode = ( AbstractGateway::TXN_TYPE_AUTHORIZE === $payment_action )
			? CaptureMode::LATER
			: CaptureMode::AUTO;

		$hpp_builder->withTransactionConfig(
			Channel::CardNotPresent,
			$store_country_code,
			$capture_mode,
			$hpp_payment_methods,
			PaymentMethodUsageMode::SINGLE
		);

		// Add currency conversion mode in order.transaction_configuration when DCC is enabled.
		if ( isset( $this->config['enable_dcc'] ) && 'yes' === $this->config['enable_dcc'] ) {
			$hpp_builder->withCurrencyConversionMode( true );
		}
		else {
			$hpp_builder->withCurrencyConversionMode( false );
		}
		// Add shipping phone if available. 
		if(property_exists( $payer, "shippingPhone" ) && $payer->shippingPhone !== "" && $payer->shippingPhone !== null ){
			$hpp_builder->withShippingPhone( $payer->shippingPhone );
		};

		if ( InstallmentsService::hpp_installments_eligible() ) {
			$hpp_builder = $this->add_installments_filtering( $hpp_builder );
		}

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
		$payer->language = strtoupper( substr( get_locale(), 0, 2 ) ) ?? "EN";
		if( property_exists( $payer, "reference" ) ){
			$payer->reference = uniqid(); // Will be removed in the future
		}
		

		// Set billing address
		$billing_address                    = new Address();
		$billing_address->streetAddress1    = $this->order->get_billing_address_1();
		$billing_address->streetAddress2    = $this->order->get_billing_address_2();
		$billing_address->city              = $this->order->get_billing_city();
		$billing_address->postalCode   = $this->order->get_billing_postcode();
		$billing_address->countryCode  = $payer_country_info['alpha2'];
		$billing_address->country      = $payer_country_info['alpha2'];

		$billing_state_code = $this->order->get_billing_state();

		if ( !empty( $billing_state_code ) && strlen( $billing_state_code ) < 4 ) {
			$billing_address->state = $billing_state_code;
		}

		$payer->billingAddress = $billing_address;

		// Set shipping address if available
		if ( $this->order->has_shipping_address() ) {
			$shipping_address                    = new Address();
			$shipping_address->streetAddress1    = $this->order->get_shipping_address_1();
			$shipping_address->streetAddress2    = $this->order->get_shipping_address_2();
			$shipping_address->city              = $this->order->get_shipping_city();
			$shipping_address->postalCode  = $this->order->get_shipping_postcode();
			$shipping_address->countryCode = $payer_country_info['alpha2'];
			$shipping_address->country     = $payer_country_info['alpha2'];

			$shipping_state_code = $this->order->get_shipping_state();

			if ( !empty( $shipping_state_code ) && strlen( $shipping_state_code ) < 4 ) {
				$billing_address->state = $shipping_state_code;
			}

			$payer->shippingAddress = $shipping_address;

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
	 * Get digital wallets configuration from admin settings.
	 *
	 * @return array containing enabled digital wallets
	 */
	protected function get_digital_wallets(): array {
		if ( ! isset( $this->config['payment_interface'] ) || 'hpp' !== $this->config['payment_interface'] ) {
			return [];
		}

		$enabled_wallets = [];

		if ( isset( $this->config['enable_gpay_hpp'] ) && 'yes' === $this->config['enable_gpay_hpp'] ) {
			$enabled_wallets[] = 'googlepay';
		}

		if ( isset( $this->config['enable_applepay_hpp'] ) && 'yes' === $this->config['enable_applepay_hpp'] ) {
			$enabled_wallets[] = 'applepay';
		}

		if ( isset( $this->config['enable_clicktopay_hpp'] ) && 'yes' === $this->config['enable_clicktopay_hpp'] ) {
			$enabled_wallets[] = apply_filters( 'globalpayments_hpp_click_to_pay_provider', 'CLICK_TO_PAY' );
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

		if ( isset( $this->config['enable_blik_hpp'] ) && wc_string_to_bool( $this->config['enable_blik_hpp'] ) ) {
			$enabled_alternative_payments[] = HPPAllowedPaymentMethods::BLIK;
		}

		if ( isset( $this->config['enable_open_banking_hpp'] ) && wc_string_to_bool( $this->config['enable_open_banking_hpp'] ) ) {
			$enabled_alternative_payments[] = HPPAllowedPaymentMethods::BANK_PAYMENT;
		}
		if ( defined( HPPAllowedPaymentMethods::class . '::' . "ERATY" ) && isset( $this->config['enable_eraty_hpp'] ) 
			&& wc_string_to_bool( $this->config['enable_eraty_hpp'] ) ) {
			$enabled_alternative_payments[] = HPPAllowedPaymentMethods::ERATY;
		}

		return $enabled_alternative_payments;
	}

	/**
	 * Removes accented characters from the site title
	 * 
	 * @param String $sitetitle containing the acccented characters
	 * @return String $sitetitle without the acccented characters
	 * 
	 */
	protected function convertToASCII( string $siteTitle ){
		return preg_replace( '/[^A-Za-z0-9\s]/' , '' , iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $siteTitle ) );
	}

	/**
	 * Adds installments filtering options to the installments shown on HPP
	 *
	 * @param HPPBuilder $hpp_builder to apply the fitering options to.
	 * @return HPPBuilder $hpp_builder with installments filtering applied if the
	 * withInstallments method applied, returns the same HPPBuilder if method not
	 * available.
	 */
	protected function add_installments_filtering( HPPBuilder $hpp_builder ): HPPBuilder
	{
		if ( !method_exists( $hpp_builder, 'withInstallments' ) ) {
			return $hpp_builder;
		}

		// Defaults
		$fundingMode = InstallmentsFundingMode::ANY;
		$maxMonths   = 32;
		$threshold   = null;

		// Funding mode
		$planType = $this->config['hpp_installments_plan_types'] ?? null;
		if ( !empty( $planType ) && $planType !== 'any' ) {
			$planType =  strtoupper( $planType );
			$InstallmentsFundingModeReflection = new \ReflectionClass( InstallmentsFundingMode::class );
			$InstallmentsFundingModeConsts = $InstallmentsFundingModeReflection->getConstants();
			
			if ( isset( $InstallmentsFundingModeConsts[$planType] ) ) {
				$fundingMode = $InstallmentsFundingModeConsts[$planType];
			} else {
				$fundingMode = InstallmentsFundingMode::ANY;
			}
		}
		
		// Max months (only relevant for merchant funded)
		if (
			$fundingMode === InstallmentsFundingMode::MERCHANT_FUNDED &&
			!empty( $this->config['hpp_installments_plan_merchant_funded_max'] ) ) {
			$raw = explode( '_', $this->config['hpp_installments_plan_merchant_funded_max'] )[0];
			$months = ( int ) $raw;

			if ( $months > 0 ) {
				$maxMonths = $months;
			}
		}

		// Threshold
		$rawThreshold = $this->config['hpp_installments_plan_threshold'] ?? null;

		if ( $rawThreshold !== null && $rawThreshold !== '0' ) {
			$thresholdValue = ( int ) $rawThreshold;
			if ( $thresholdValue > 0 && strlen( ( string ) $rawThreshold) < 16 ) {
				$threshold = $thresholdValue;
			}
		}

		return $hpp_builder->withInstallments( $fundingMode, $maxMonths, $threshold );
	}
}
