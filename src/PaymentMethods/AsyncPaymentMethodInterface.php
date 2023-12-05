<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\PaymentMethods;

defined( 'ABSPATH' ) || exit;

interface AsyncPaymentMethodInterface {
	public function add_hooks();
	public function init_form_fields();
	public function is_available();
	public function get_provider_endpoints();
	public function process_payment($order_id);
	public function thankyou_order_received_text($text);
}
