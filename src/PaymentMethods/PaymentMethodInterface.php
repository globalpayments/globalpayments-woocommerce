<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods;

defined( 'ABSPATH' ) || exit;

interface PaymentMethodInterface {
	public function configure_method_settings();
	public function get_payment_method_form_fields();
	public function get_frontend_payment_method_options();
	public function get_request_type();
}
