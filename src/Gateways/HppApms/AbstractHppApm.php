<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HppApms;

use Automattic\WooCommerce\Internal\DependencyManagement\ContainerException;
use Exception;
use Automattic\WooCommerce\Caching\CacheException;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits\HppTrait;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\HppResponseParser;
use WC_Order;
use WC_Order_Refund;
use WP_Exception;

defined('ABSPATH') || exit;

/**
 * Abstract HPP APM Handler
 * 
 * Handles status notifications and redirects for HPP-based alternative payment methods (BLIK, Open Banking, etc.)
 * Similar to DiUiApms\AbstractApm but specifically for HPP payments
 * 
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\HppApms
 * @since 1.15.0
 */
class AbstractHppApm {

    /**
     * Handle HPP payment status callback from payment gateway
     * 
     * This method processes status updates for all HPP-based payments including:
     * 
     * @return void 
     * @throws WP_Exception 
     * @throws ContainerException 
     * @throws Exception 
     * @throws CacheException 
     */
    public static function handle_hpp_status_notification(): void
    {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp_status'];
        
        $gateway = new GpApiGateway();
        
        // Note: Signature validation is handled in HppTrait::process_hpp_status() before this method is called
        
        if ( $gateway->debug ) {
            $logger->info( 'HPP Status: Starting notification processing', $context );
        }
        
        // Get request data
        $request_data = array_merge( $_GET, $_POST );

        // Parse JSON body if present
        $raw_input = file_get_contents( 'php://input' );
        if ( !empty( $raw_input )) {
            $json_data = json_decode( $raw_input, true );
            if ( $json_data ) {
                $request_data = array_merge( $request_data, $json_data );
            }
        }

        // Extract transaction details using HppResponseParser
        $transaction_id = HppResponseParser::extract_transaction_id( $request_data );
        $payment_status = HppResponseParser::extract_payment_status( $request_data );
        $order_id       = HppResponseParser::extract_order_id( $request_data );
        

        if ( empty( $order_id ) ) {
            if ($gateway->debug) {
                $logger->error('HPP Status: Order ID not found in request');
            }
            wp_die( 'Order not found', 404 );
            return;
        }
        

        // Find the order
        $order = wc_get_order( $order_id );
        
        if ( !$order instanceof WC_Order ) {
            if ( $gateway->debug ) {
                $logger->error( 'HPP Status: Invalid order' );
            }
            wp_die( 'Invalid order', 404 );
            return;
        }
        

        // Verify transaction ID matches 
        if ( !empty( $order->get_transaction_id() ) && $order->get_transaction_id() !== $transaction_id ) {
            if ( $gateway->debug ) {
                $logger->warning( 'HPP Status: Transaction ID mismatch' );
            }
            // Don't process if transaction IDs don't match 
            wp_die( 'OK', 200 );
            return;
        }
        
        // Update order status based on payment status
        self::update_order_status_from_notification( $order, $payment_status, $transaction_id, $request_data, $gateway );

        wp_die( 'OK', 200 );
    }

    /**
     * Update order status based on payment notification
     * 
     * @param WC_Order $order 
     * @param string $payment_status 
     * @param string $transaction_id 
     * @param array $callback_data 
     * @param GpApiGateway $gateway
     * @return void 
     */
    protected static function update_order_status_from_notification(
        WC_Order $order,
        string $payment_status,
        string $transaction_id,
        array $callback_data,
        GpApiGateway $gateway
    ): void {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp_status'];
        
        $status_upper = strtoupper( $payment_status );

        // Create order note with callback details based
        $callback_summary = "Status: $payment_status";
        if ( !empty( $transaction_id ) ) {
            $callback_summary .= ", Transaction ID: $transaction_id";
        }
        if ( isset( $callback_data['amount'] ) ) {
            $callback_summary .= ", Amount: " . $callback_data['amount'];
        }
        if ( isset( $callback_data['currency'] ) ) {
            $callback_summary .= " " . $callback_data['currency'];
        }

        // Extract payment method details 
        $payment_method_type = HppResponseParser::extract_payment_method_type( $callback_data );
        if ( !empty( $payment_method_type ) ) {
            $callback_summary .= ", Payment Method: $payment_method_type";
        }

        $payment_result_code = HppResponseParser::extract_payment_result_code( $callback_data );
        if ( !empty( $payment_result_code ) ) {
            $callback_summary .= ", Result Code: $payment_result_code";
        }

        $payment_message = HppResponseParser::extract_payment_message( $callback_data );
        if ( !empty( $payment_message ) ) {
            $callback_summary .= ", Message: $payment_message";
        }

        switch ( $status_upper ) {
            case 'PREAUTHORIZED':
                // Payment authorized but not yet captured
                if ( !in_array( $order->get_status(), ['processing', 'completed'] ) ) {
                    $note_text = sprintf(
                        __( 'Payment authorized via HPP. %s', 'globalpayments-gateway-provider-for-woocommerce' ),
                        $callback_summary
                    );
                    $order->update_status( 'processing', $note_text );
                    
                    // Set transaction ID if not already set
                    if ( empty( $order->get_transaction_id() ) ) {
                        $order->set_transaction_id( $transaction_id );
                    }

                    // Add metadata to track callback processing
                    $order->update_meta_data( '_globalpayments_hpp_callback_processed', date( 'Y-m-d H:i:s' ) );
                    $order->update_meta_data( '_globalpayments_hpp_payment_status', $payment_status );
                    if ( !empty( $payment_method_type ) ) {
                        $order->update_meta_data( '_globalpayments_hpp_payment_method_type', $payment_method_type );
                    }
                    $order->save();

                }
                break;

            case 'CAPTURED':
                // Payment successfully captured
                if ( !in_array( $order->get_status(), ['processing', 'completed'] ) ) {
                    $note_text = sprintf(
                        __( 'Payment completed via HPP. %s', 'globalpayments-gateway-provider-for-woocommerce' ),
                        $callback_summary
                    );
                    $order->add_order_note( $note_text );
                    $order->payment_complete( $transaction_id );

                    // Add metadata to track callback processing
                    $order->update_meta_data( '_globalpayments_hpp_callback_processed', date( 'Y-m-d H:i:s' ) );
                    $order->update_meta_data( '_globalpayments_hpp_payment_status', $payment_status );
                    $order->update_meta_data( '_globalpayments_payment_captured', 'is_captured' );
                    if ( !empty( $payment_method_type ) ) {
                        $order->update_meta_data( '_globalpayments_hpp_payment_method_type', $payment_method_type );
                    }
                    $order->save();

                    if ( $gateway->debug ) {
                        $logger->info( 'HPP Status: Payment completed', array_merge($context, [
                            'order_id' => $order->get_id(),
                            'transaction_id' => $transaction_id
                        ] ) );
                    }
                }
                break;
            case 'PENDING' : 
                if ( in_array( $order->get_status(), ['on-hold', 'pending', 'cancelled', 'failed'] ) ) {

                 $note_text = sprintf(
                        __('Payment pending via HPP. %s', 'globalpayments-gateway-provider-for-woocommerce'),
                        $callback_summary
                    );
                    if( "PENDING" ===  strtoupper( $order->get_status() ) ){
                        $order->add_order_note( $note_text );
                    }else{
                        $order->update_status( "pending", $note_text );
                    }


                     // Add metadata to track callback processing
                    $order->update_meta_data( '_globalpayments_hpp_callback_processed', date( 'Y-m-d H:i:s' ) );
                    $order->update_meta_data( '_globalpayments_hpp_payment_status', $payment_status );
                    $order->update_meta_data( '_globalpayments_payment_captured', 'is_pending' );
                    if ( !empty( $payment_method_type ) ) {
                        $order->update_meta_data( '_globalpayments_hpp_payment_method_type', $payment_method_type );
                    }
                    $order->save();
                       if ( $gateway->debug ) {
                            $logger->info( 'HPP Status: Payment Pending', array_merge( $context, [
                                'order_id' => $order->get_id(),
                                'transaction_id' => $transaction_id
                            ]
                        ));
                    }
                }
                break;
            case 'DECLINED':
            case 'CANCELLED':
            case 'FAILED':
                // Payment failed, declined, or cancelled
                if ( in_array($order->get_status(), ['on-hold', 'pending', 'cancelled', 'failed'] ) ) {
                    $note_text = sprintf(
                        __( 'Payment failed/declined via HPP status notification. %s', 'globalpayments-gateway-provider-for-woocommerce' ),
                        $callback_summary
                    );
                    $order->update_status( 'cancelled', $note_text );

                    // Add metadata to track callback processing
                    $order->update_meta_data( '_globalpayments_hpp_callback_processed', date('Y-m-d H:i:s') );
                    $order->update_meta_data( '_globalpayments_hpp_payment_status', $payment_status );
                    if ( !empty( $payment_method_type ) ) {
                        $order->update_meta_data( '_globalpayments_hpp_payment_method_type', $payment_method_type );
                    }
                    $order->save();

                    if ( $gateway->debug ) {
                        $logger->info( 'HPP Status: Payment failed/cancelled', array_merge( $context, [
                            'order_id' => $order->get_id(),
                            'status' => $payment_status
                        ] ) );
                    }
                }
                break;

            default:
                // Unknown status - log it
                if ( $gateway->debug ) {
                    $logger->warning( 'HPP Status: Unknown payment status', array_merge( $context, [
                        'order_id' => $order->get_id(),
                        'status' => $payment_status,
                        'callback_data' => $callback_data
                    ] ) );
                }
                $order->add_order_note(
                    sprintf(
                        __( 'HPP status notification received with unknown status: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
                        $callback_summary
                    )
                );
                $order->save();
                break;
        }
    }

    /**
     * Extract payment method type from callback data
     * 
     * - payment_method.entry_mode: "ECOM" (for card payments)
     * - payment_method.result: "100" (result code)
     * - payment_method.message: "FAILED" (result message)
     * 
     * @param array $data 
     * @return string|null 
     */
    protected static function extract_payment_method_type( array $data ): ?string
    {
        // Extract entry_mode which indicates the payment method type
        if ( !empty( $data['payment_method']['entry_mode'] ) ) {
            return sanitize_text_field( $data['payment_method']['entry_mode'] );
        }

        return null;
    }

    /**
     * Extract payment method result code from callback data
     * 
     * payment_method.result: "00" (success) or "100" (declined)
     * 
     * @param array $data 
     * @return string|null 
     */
    protected static function extract_payment_result_code( array $data ): ?string
    {
        if ( !empty( $data['payment_method']['result'] ) ) {
            return sanitize_text_field( $data['payment_method']['result'] );
        }

        return null;
    }

    /**
     * Extract payment method message from callback data
     * 
     * 
     * - payment_method.message: "FAILED" | "SUCCESS" | other gateway messages
     * 
     * @param array $data 
     * @return string|null 
     */
    protected static function extract_payment_message( array $data ): ?string
    {
        if ( !empty($data['payment_method']['message'] ) ) {
            return sanitize_text_field( $data['payment_method']['message'] );
        }

        return null;
    }

}