<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\Subscriptions;

use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\AbstractRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;

use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\Api\Entities\Enums\PhoneNumberType;
use GlobalPayments\Api\Entities\PhoneNumber;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;




defined('ABSPATH') || exit;

class SubscriptionRequest extends AbstractRequest
{

    public function get_transaction_type()
    {
        return AbstractGateway::TXN_TYPE_SUBSCRIPTION_PAYMENT;
    }
    public function get_args()
    {
        return array();
    }
    public function do_request()
    {
        $muti_use_token = $this->get_muti_use_token();
        $tokenizedCard = new CreditCardData();
        $tokenizedCard->token = $muti_use_token;
        $customer_details = $this->get_customer_data();
        $tokenizedCard->cardHolderName = $customer_details->firstName . " " . $customer_details->lastName;
        $response = $tokenizedCard->charge($this->order->get_total())
            ->withCurrency($this->order->get_currency())
            ->withOrderId((string) $this->order->get_id())
            ->withAddress($this->get_shipping_details(), AddressType::SHIPPING)
            ->withAddress($this->get_billing_address(), AddressType::BILLING)
            ->withCustomerData($customer_details)
            ->withDynamicDescriptor("Subscription payment for order: " . $this->order->get_id())

            ->execute();

        return $response;
    }
    private function get_muti_use_token()
    {
        return $this->order->get_meta("_GP_multi_use_token") ? $this->order->get_meta("_GP_multi_use_token") : $this->config['multi_use_token'];
    }
    private function get_shipping_details()
    {
        $shipping_details = new Address();
        $shipping_details->streetAddress1 = $this->order->get_billing_address_1();
        $shipping_details->streetAddress2 = $this->order->get_billing_address_2();
        $shipping_details->city           = $this->order->get_billing_city();
        $shipping_details->state          = $this->order->get_billing_state();
        $shipping_details->postalCode     = $this->order->get_billing_postcode();
        $shipping_details->country        = $this->order->get_billing_country();
        return $shipping_details;
    }
    protected function get_billing_address()
    {
        $billing_address = new Address();
        $billing_address->streetAddress1 = $this->order->get_billing_address_1();
        $billing_address->streetAddress2 = $this->order->get_billing_address_2();
        $billing_address->city           = $this->order->get_billing_city();
        $billing_address->state          = $this->order->get_billing_state();
        $billing_address->postalCode     = $this->order->get_billing_postcode();
        $billing_address->country        = $this->order->get_billing_country();

        return $billing_address;
    }
    private function get_customer_data()
    {
        $customer            = new Customer();
        $customer->id        = $this->order->get_customer_id();
        $customer->firstName = Utils::sanitize_string($this->order->get_billing_first_name());
        $customer->lastName  = Utils::sanitize_string($this->order->get_billing_last_name());
        $customer->email     = $this->order->get_billing_email();
        $phone_code          = CountryUtils::getPhoneCodesByCountry($this->order->get_billing_country());
        $customer->phone     = new PhoneNumber($phone_code[0], $this->order->get_billing_phone(), PhoneNumberType::HOME);

        return $customer;
    }
}
