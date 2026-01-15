<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Utils;

defined('ABSPATH') || exit;

/**
 * HPP Response Parser
 * 
 * Utility class for parsing HPP gateway responses
 * Contains static methods for extracting data from gateway callbacks
 * 
 * @package GlobalPayments\WooCommercePaymentGatewayProvider\Utils
 * @since 1.15.0
 */
class HppResponseParser {

    /**
     * Extract order ID from gateway response
     * 
     * - link_data.reference
     *
     * @param array $gateway_data
     * @return int
     */
    public static function extract_order_id( array $gateway_data ): int
    {
        // Try to extract from link_data.reference field first (nested structure)
        if ( !empty( $gateway_data['link_data']['reference'] ) ) {
            $reference = sanitize_text_field( $gateway_data['link_data']['reference'] );
            
            if ( preg_match( '/Order #(\d+)/', $reference, $matches ) ) {
                return (int) $matches[1];
            }
        }
        
        // Fallback: Try top-level reference field
        if ( !empty( $gateway_data['reference'] ) ) {
            $reference = sanitize_text_field( $gateway_data['reference'] );
            
            if ( preg_match( '/Order #(\d+)/', $reference, $matches ) ) {
                return (int) $matches[1];
            }
        }

        // Last resort: try to get from GET parameters
        return (int) ($_GET['order_id'] ?? 0);
    }

    /**
     * Extract transaction ID from HPP callback data
     * 
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extract_transaction_id( array $data ): ?string
    {
        // The transaction ID is always in the top-level 'id' field
        if ( isset( $data['id'] ) && !empty( $data['id'] ) ) {
            return sanitize_text_field( $data['id'] );
        }

        return null;
    }

    /**
     * Extract payment status from HPP callback data
     * 
     * - status: "DECLINED" | "CAPTURED" | "PREAUTHORIZED" 
     * 
     * @param array $data 
     * @return string 
     */
    public static function extract_payment_status( array $data ): string
    {
        if ( isset( $data['status'] ) && !empty( $data['status'] ) ) {
            return sanitize_text_field( $data['status'] );
        }

        return 'UNKNOWN';
    }

    /**
     * Extract payment method type from HPP callback data
     * 
     * - payment_method.entry_mode: "ECOM" 
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extract_payment_method_type( array $data ): ?string
    {
        // Extract entry_mode which indicates the payment method type
        if ( !empty( $data['payment_method']['entry_mode'] ) ) {
            return sanitize_text_field( $data['payment_method']['entry_mode'] );
        }

        return null;
    }

    /**
     * Extract payment method result code from HPP callback data
     * 
     * - payment_method.result: "00" (success) or "100" (declined)
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extract_payment_result_code( array $data ): ?string
    {
        if ( !empty( $data['payment_method']['result'] ) ) {
            return sanitize_text_field( $data['payment_method']['result'] );
        }

        return null;
    }

    /**
     * Extract payment method message from HPP callback data
     * 
     * - payment_method.message: "FAILED" | "SUCCESS" | other gateway messages
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extract_payment_message( array $data ): ?string
    {
        if ( !empty( $data['payment_method']['message'] ) ) {
            return sanitize_text_field( $data['payment_method']['message'] );
        }

        return null;
    }

    /**
     * Determine if HPP payment was successful from gateway response
     *
     * @param array $gateway_data
     * @return bool true if successful, false otherwise
     */
    public static function is_successful_payment( array $gateway_data ): bool
    {
        $status = $gateway_data['status'] ?? '';
        $result_code = $gateway_data['payment_method']['result'] ?? '';
        $action_result = $gateway_data['action']['result_code'] ?? '';
        
        $is_successful = $status === 'CAPTURED' && $result_code === '00' && $action_result === 'SUCCESS';
        
        return $is_successful;
    }

    /**
     * Determine if HPP payment is in a pending state from gateway response
     *
     * @param array $gatway_data
     * @return bool true if order is in pending status
     */

    public static function is_pending_payment( array $gateway_data ): bool
    {
        $status = $gateway_data['status'] ?? '';
        $result_code = $gateway_data['payment_method']['result'] ?? '';
        $action_result = $gateway_data['action']['result_code'] ?? '';

        $is_pending = $status === 'PENDING' && $result_code === '01' && $action_result === 'SUCCESS';
        
        return $is_pending;
    }


    /**
     * Get error message from gateway response
     *
     * @param array $gateway_data
     * @return string containing error message
     */
    public static function get_error_message( array $gateway_data ): string
    {
        return $gateway_data['payment_method']['message'] ?? 
               __('Payment failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce');
    }
}