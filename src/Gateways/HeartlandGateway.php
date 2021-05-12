<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways;

use WC_Order;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HeartlandGiftCards\HeartlandGiftCardOrder;

defined( 'ABSPATH' ) || exit;

class HeartlandGateway extends AbstractGateway {
	public $gateway_provider = GatewayProvider::PORTICO;

	/**
	 * Merchant location public API key
	 *
	 * Used for single-use tokenization on frontend
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * Merchant location secret API key
	 *
	 * Used for gateway transactions on backend
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Allows payment via Heartland Marketing Solutions (gift cards)
	 *
	 * @var bool
	 */
	public $allow_gift_cards;	
	
	public function configure_method_settings() {
		$this->id                 = 'globalpayments_heartland';
		$this->method_title       = __( 'Heartland', 'globalpayments-gateway-provider-for-woocommerce' );
		$this->method_description = __( 'Connect to the Heartland Portico gateway', 'globalpayments-gateway-provider-for-woocommerce' );
	}

	public function get_first_line_support_email() {
		return 'onlinepayments@heartland.us';
	}

	public function get_gateway_form_fields() {
	    
		return array(
			'public_key' => array(
				'title'       => __( 'Public Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Heartland Online Payments account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),
			'secret_key' => array(
				'title'       => __( 'Secret Key', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API keys from your Heartland Online Payments account.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'default'     => '',
			),			
			'allow_gift_cards' => array(
				'title'				=> __( 'Enable Gift Cards', 'globalpayments-gateway-provider-for-woocommerce' ),
				'label'				=> __( 'Allow customers to use gift cards to pay for purchases in full or in part.', 'globalpayments-gateway-provider-for-woocommerce' ),
				'type'				=> 'checkbox',
				'description'		=> sprintf(
					__( 'This will display a gift card entry field directly below the credit card entry area.' )
				),
				'default'			=> 'no'
			),
		    'check_avs_cvv' => array(
		        'title'				=> __( 'Check AVS CVN', 'globalpayments-gateway-provider-for-woocommerce' ),
		        'label'				=> __( 'Check AVS/CVN result codes and reverse transaction.', 'globalpayments-gateway-provider-for-woocommerce' ),
		        'type'				=> 'checkbox',
		        'description'		=> sprintf(
		            __( 'This will check AVS/CVN result codes and reverse transaction.' )
		            ),
		        'default'			=> 'no'
		    ),
		    'avs_reject_conditions'    => array(
		        'title'       => __( 'AVS Reject Conditions', 'globalpayments-gateway-provider-for-woocommerce' ),
		        'type'        => 'multiselect',
		        'description' => __( 'Choose for which AVS result codes, the transaction must be auto reveresed.'),
		        'options'     => $this->avs_rejection_conditions(),
		    ),
		    'cvn_reject_conditions'    => array(
		        'title'       => __( 'CVN Reject Conditions', 'globalpayments-gateway-provider-for-woocommerce' ),
		        'type'        => 'multiselect',
		        'description' => __( 'Choose for which CVN result codes, the transaction must be auto reveresed.'),
		        'options'     => $this->cvn_rejection_conditions(),
		    ),
		);
	}

	public function get_frontend_gateway_options() {
		return array(
			'publicApiKey' => $this->public_key,
		);
	}

	public function get_backend_gateway_options() {
		return array(
			'secretApiKey' => $this->secret_key,
			'versionNumber' => '1510',
			'developerId' => '002914'
		);
	}

	protected function is_transaction_active( TransactionSummary $details ) {
		// @phpcs:ignore WordPress.NamingConventions.ValidVariableName
		return 'A' === $details->transactionStatus;
	}

	/**
	 * returns decline message for display to customer
	 * 
	 * @param string $response_code
	 *
	 * @return string
	 */
	public function get_decline_message( string $response_code ) {
		switch ($response_code) {
			case '02':
			case '03':
			case '04':
			case '05':
			case '41':
			case '43':
			case '44':
			case '51':
			case '56':
			case '61':
			case '62':
			case '62':
			case '63':
			case '65':
			case '78':
				return 'The card was declined.';
			case '06':
			case '07':
			case '12':
			case '15':
			case '19':
			case '52':
			case '53':
			case '57':
			case '58':
			case '76':
			case '77':
			case '96':
			case 'EC':
				return 'An error occured while processing the card.';
			case '13':
				return 'Must be greater than or equal 0.';
			case '14':
				return 'The card number is incorrect.';
			case '54':
				return 'The card has expired.';
			case '55':
				return 'The pin is invalid.';
			case '75':
				return 'Maximum number of pin retries exceeded.';
			case '80':
				return 'Card expiration date is invalid.';
			case '86':
				return 'Can\'t verify card pin number.';
			case '91':
				return 'The card issuer timed-out.';
			case 'EB':
			case 'N7':
				return 'The card\'s security code is incorrect.';
			case 'FR':
				return 'Possible fraud detected.';
			default:
				return 'An error occurred while processing the card.';
		}
	}

	/**
	 * Add gift card fields if enabled
	 * 
	 */
	public function payment_fields() {
		parent::payment_fields();

		if ( $this->allow_gift_cards === true ) {
			$path = dirname(plugin_dir_path(__FILE__));

			include_once  $path . '/../assets/frontend/HeartlandGiftFields.php';
			wp_enqueue_style('heartland-gift-cards', $path . '/../assets/frontend/css/heartland-gift-cards.css');
		}
	}

	/**
	 * Handle payment functions
	 *
	 * @param int $order_id
	 *
	 * @return array	 * 
	 */
	public function process_payment( $order_id ) {
	    $order         = new WC_Order( $order_id );
		$request       = $this->prepare_request( $this->payment_action, $order );
		$response      = $this->submit_request( $request );
		$is_successful = $this->handle_response( $request, $response );
		
		//reverse incase of AVS/CVN failure
		if(!empty($response->transactionReference->transactionId) && !empty($this->check_avs_cvv)){
		    if(!empty($response->avsResponseCode) || !empty($response->cvnResponseCode)){	
		        //check admin selected decline condtions
	            if(in_array($response->avsResponseCode, $this->avs_reject_conditions) ||
	                in_array($response->cvnResponseCode, $this->cvn_reject_conditions)){
	                    Transaction::fromId( $response->transactionReference->transactionId )
	                    ->reverse( $request->order->data[ 'total' ] )
	                    ->execute();
	                    
	                    $is_successful = false;
	            }
		    } 
		}
		
		// Charge HPS gift cards if CC trans succeeds
		if ( $is_successful && !empty( WC()->session->get( 'heartland_gift_card_applied' ) ) ) {
			$gift_card_order_placement = new HeartlandGiftCardOrder();
			$gift_payments_successful = $gift_card_order_placement->processGiftCardPayment( $order_id );

			// reverse the CC transaction if GC transactions didn't didn't succeed
			if (!$gift_payments_successful) {			
				if ($gift_card_order_placement !== false) {

				// hook directly into GP SDK to avoid collisions with the existing request
				Transaction::fromId( $response->transactionReference->transactionId )
					->reverse( $request->order->data[ 'total' ] )
					->execute();

				$is_successful = false;
				}
			} 
		}

		return array(
			'result'   => $is_successful ? 'success' : 'failure',
			'redirect' => $is_successful ? $this->get_return_url( $order ) : false,
		);
	}
	
	public function avs_rejection_conditions()
	{
	    return array(
	        'A'  => 'Address matches, zip No Match',
	        'N'  => 'Neither address or zip code match',
	        'R'  => 'Retry - system unable to respond',
	        'U'  => 'Visa / Discover card AVS not supported',
	        'S'  => 'Master / Amex card AVS not supported',
	        'Z'  => 'Visa / Discover card 9-digit zip code match, address no match',
	        'W'  => 'Master / Amex card 9-digit zip code match, address no match',
	        'Y'  => 'Visa / Discover card 5-digit zip code and address match',
	        'X'  => 'Master / Amex card 5-digit zip code and address match',
	        'G'  => 'Address not verified for International transaction',
	        'B'  => 'Address match, Zip not verified',
	        'C'  => 'Address and zip mismatch',
	        'D'  => 'Address and zip match',
	        'I'  => 'AVS not verified for International transaction',
	        'M'  => 'Street address and postal code matches',
	        'P'  => 'Address and Zip not verified'
	    );
	}
	
	public function cvn_rejection_conditions()
	{
	    return array(
	        'N' => 'Not Matching',
	        'P' => 'Not Processed',
	        'S' => 'Result not present',
	        'U' => 'Issuer not certified',
	        '?' => 'CVV unrecognized'
	    );
	}
}
