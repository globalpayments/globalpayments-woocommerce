<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\DiUiApms;

use Automattic\WooCommerce\Internal\DependencyManagement\ContainerException;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use GlobalPayments\Api\Entities\Enums\{AlternativePaymentType, Channel, Environment, ServiceEndpoints};
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\PaymentMethods\AlternativePaymentMethod;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use WC_Order;

defined('ABSPATH') || exit;

/**
 * Helper class for DiUi/BankSelect transactions
 * 
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\DiUiApms
 */
class BankSelect extends AbstractApm
{
	/**
	 * 
	 * @param GpApiGateway $gateway 
	 * @param int $order_id 
	 * @return array 
	 * @throws ContainerException 
	 * @throws Exception 
	 */
	public static function process_bank_select_sale( GpApiGateway $gateway, int $order_id ) : array
	{
		$order = new WC_Order( $order_id );

		// Mark as on-hold (we're awaiting the payment confirmation)
		$order->update_status( 'on-hold', __( 'Awaiting Open Banking payment.', 'globalpayments-gateway-provider-for-woocommerce' ) );

		$settings = $gateway->get_backend_gateway_options();

		$config = new GpApiConfig();
		$config->country = WC()->countries->get_base_country();
		$config->appId = $settings["appId"];
		$config->appKey = $settings["appKey"];

		$config->serviceUrl = $settings["environment"] === Environment::PRODUCTION ?
			ServiceEndpoints::GP_API_PRODUCTION : ServiceEndpoints::GP_API_TEST;
		$config->channel = Channel::CardNotPresent;

		$accessTokenInfo = new AccessTokenInfo();
		$accessTokenInfo->transactionProcessingAccountName = $settings["environment"] === Environment::PRODUCTION ?
			$gateway->settings["account_name"] : $gateway->settings["sandbox_account_name"];

		$config->accessTokenInfo = $accessTokenInfo;

		ServicesContainer::configureService( $config );

		$paymentMethod = new AlternativePaymentMethod( AlternativePaymentType::OB );
		$paymentMethod->bank = $_POST["open_banking"];
		$paymentMethod->descriptor = 'ORD' . $order->get_id();
		$paymentMethod->country = $order->get_billing_country();
		$paymentMethod->accountHolderName = $order->get_formatted_billing_full_name();

		$paymentMethod->returnUrl = WC()->api_request_url( 'globalpayments_bank_select_redirect_handler' );
		$paymentMethod->statusUpdateUrl = WC()->api_request_url( 'globalpayments_bank_select_status_handler' );
		$paymentMethod->cancelUrl = wc_get_checkout_url(); // currently not supported by gateway

		try {
			// Get cart details with fallbacks
			$cartTotal = WC()->cart->total;
			$currency = get_woocommerce_currency() ?: 'PLN';

			$bankSelectGpResponse = $paymentMethod->charge( $cartTotal )
				->withClientTransactionId( 'WooCommerce_Order_' . $order->get_order_number() )
				->withCurrency( $currency )
				->execute();
		} catch ( \Exception $e ) {
			error_log( 'Open Banking Payment Error: ' . $e->getMessage() );
			error_log( 'Open Banking Payment Error Code: ' . $e->getCode() );
			error_log( 'Open Banking Payment Stack Trace: ' . $e->getTraceAsString() );
			throw new ApiException( esc_html__( $e->getMessage(), 'globalpayments-gateway-provider-for-woocommerce' ) );
		}

		$meta = array(
			'amount'         => $order->get_total(),
			'currency'       => $order->get_currency(),
			'invoice_number' => $order->get_id(),
		);

		$order->set_transaction_id( $bankSelectGpResponse->transactionId );
		$order->save();

		foreach ( $meta as $key => $value ) {
			if (OrderUtil::custom_orders_table_usage_is_enabled()) {
				$order->update_meta_data( sprintf( '_globalpayments_%s', $key ), $value );
			} else {
				update_post_meta( $order->get_id(), sprintf( '_globalpayments_%s', $key ), $value );
			}
		}

		$order->save_meta_data();

		$order->add_order_note(
			sprintf(
				'%1$s%2$s %3$s. Transaction ID: %4$s.',
				get_woocommerce_currency_symbol( $order->get_currency() ),
				$order->get_total(),
				__( 'initiated', 'globalpayments-gateway-provider-for-woocommerce' ),
				$bankSelectGpResponse->transactionId,
			)
		);

		return array(
			'result'   => 'success',
			'redirect' => $bankSelectGpResponse->alternativePaymentResponse->redirectUrl,
		);
	}
}
