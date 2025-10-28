<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Services;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Service for installments display and emails
 * 
 * @since 1.14.9
 */
class InstallmentsService {
    
    /**
     * Check if order has installments
     *
     * @param WC_Order $order
     * @return bool true if order has installments, false otherwise
     */
    public static function order_has_installments( WC_Order $order ): bool {
        return $order->get_meta( '_globalpayments_installment_data' ) ? true : false;
    }
    
    /**
     * Get installment data for order
     *
     * @param WC_Order $order
     * @return array|null
     */
    public static function get_order_installment_data( WC_Order $order ): ?array {
        if ( ! self::order_has_installments( $order ) ) {
            return null;
        }
        
        $meta_data = $order->get_meta( '_globalpayments_installment_data' );
        return is_array( $meta_data ) ? $meta_data : null;
    }
    
    /**
     * Format amount for display
     * 
     * @param int $amount 
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    public static function format_amount( int $amount, string $currency = 'GBP' ): string {
        $major_amount = $amount / 100;
        
        // Format based on currency
        switch ( strtoupper( $currency ) ) {
            case 'GBP':
                return '£' . number_format( $major_amount, 2 );
            case 'USD':
                return '$' . number_format( $major_amount, 2 );
            case 'EUR':
                return '€' . number_format( $major_amount, 2 );
            default:
                return $currency . ' ' . number_format( $major_amount, 2 );
        }
    }

    /**
     * Render installments information in plaintext 
     *
     * @param WC_Order $order
     * @return void
     */
    public static function render_installments_info_plaintext( WC_Order $order ): void {
        
        echo "\n" . __( 'INSTALLMENT PLAN DETAILS', 'globalpayments-gateway-provider-for-woocommerce' ) . "\n";
        echo str_repeat( '=', 50 ) . "\n";
        
        $installment_data = self::get_order_installment_data( $order );
        if ( $installment_data ) {
            // Extract terms data
            $terms_data = $installment_data['terms'] ?? $installment_data;
            
            if ( ! empty( $terms_data['count'] ) && ! empty( $terms_data['time_unit'] ) ) {
                echo sprintf( __( 'Payment Plan: %d %s payments', 'globalpayments-gateway-provider-for-woocommerce' ), 
                    $terms_data['count'], 
                    strtolower( $terms_data['time_unit'] ) ) . "\n";
            }
            
            echo sprintf( __( 'Original Amount: %s', 'globalpayments-gateway-provider-for-woocommerce' ), 
                wp_strip_all_tags( $order->get_formatted_order_total() ) ) . "\n";
            
            if ( ! empty( $terms_data['amount'] ) ) {
                echo sprintf( __( 'Monthly Payment: %s', 'globalpayments-gateway-provider-for-woocommerce' ), 
                    self::format_amount( $terms_data['amount'], $order->get_currency() ) ) . "\n";
            }
            
            if ( ! empty( $terms_data['interest_percentage'] ) ) {
                echo sprintf( __( 'Interest Rate: %s%%', 'globalpayments-gateway-provider-for-woocommerce' ), 
                    number_format( $terms_data['interest_percentage'], 2 ) ) . "\n";
            }
        }
        
        echo "\n";
    }
    
    
    /**
     * Render installments information for order success page
     *
     * @param WC_Order $order
     * @return string
     */
    public static function render_installments_info( WC_Order $order ): string {
        $installment_data = self::get_order_installment_data( $order );
        
        if ( ! $installment_data ) {
            return '';
        }
        
        $terms_data = $installment_data['terms'] ?? $installment_data;
        
        // Validate required fields exist
        if ( empty( $terms_data['count'] ) ) {
            return '';
        }

        // Get Global Payments logo URL
        $gp_logo_url = \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::get_url( '/assets/frontend/img/globalpayments-logo.png' );
        
        ob_start();
        ?>
        <!-- Installments Info Modal Trigger Button -->
        <div class="gp-installments-trigger">
            <button id="gp-installments-btn" class="gp-installments-button">
                <?php esc_html_e( 'View Installment Plan Details', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
            </button>
        </div>

        <!-- Installments Info Modal -->
        <div id="gp-installments-modal" class="gp-modal-overlay">
            <div class="gp-modal-container">
                <div class="gp-modal-header">
                    <?php if ( ! empty( $gp_logo_url ) ): ?>
                        <img src="<?php echo esc_url( $gp_logo_url ); ?>" alt="GlobalPayments" class="gp-modal-logo">
                    <?php endif; ?>
                    <h3><?php esc_html_e( 'Installment Plan Details', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h3>
                    <button class="gp-modal-close" id="gp-modal-close" aria-label="Close modal">&times;</button>
                </div>
                
                <div class="gp-modal-content">
                    <div class="gp-installment-summary">
                        <div class="gp-installment-badge">
                            <span class="gp-plan-months"><?php echo esc_html( $terms_data['count'] ); ?></span>
                            <span class="gp-plan-label"><?php esc_html_e( 'Month Plan', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                        </div>
                        
                        <div class="gp-installment-details">
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Payment Plan:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value">
                                    <?php echo esc_html( $terms_data['count'] ); ?> 
                                    <?php echo esc_html( strtolower( $terms_data['time_unit'] ) ); ?> 
                                    <?php esc_html_e( 'payments', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                </span>
                            </div>
                            
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Original Amount:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                            </div>
                            
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Total Plan Cost:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( self::format_amount( $terms_data['total_plan_cost'] ?? 0, $terms_data['currency'] ?? 'GBP' ) ); ?></span>
                            </div>
                            
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Installment Fees:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( self::format_amount( $terms_data['fees']['total_amount'] ?? 0, $terms_data['currency'] ?? 'GBP' ) ); ?></span>
                            </div>
                            
                            <div class="gp-detail-row gp-highlight">
                                <span class="gp-label"><?php esc_html_e( 'Interest Rate:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( $terms_data['cost_percentage'] ?? '0' ); ?>% <?php esc_html_e( 'APR', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                            </div>
                            
                            <?php if ( ! empty( $terms_data['total_amount'] ) ): ?>
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Total Amount:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( self::format_amount( $terms_data['total_amount'], $terms_data['currency'] ?? 'GBP' ) ); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $terms_data['time_unit'] ) ): ?>
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Payment Frequency:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( ucfirst( strtolower( $terms_data['time_unit'] ) ) ); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ( ! empty( $terms_data['currency'] ) ): ?>
                            <div class="gp-detail-row">
                                <span class="gp-label"><?php esc_html_e( 'Currency:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                                <span class="gp-value"><?php echo esc_html( strtoupper( $terms_data['currency'] ) ); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ( ! empty( $terms_data['description'] ) ): ?>
                    <div class="gp-installment-description">
                        <p><?php echo esc_html( $terms_data['description'] ); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $terms_data['terms_and_conditions_url'] ) ): ?>
                    <div class="gp-installment-terms">
                        <a href="<?php echo esc_url( $terms_data['terms_and_conditions_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="gp-terms-link">
                            <?php esc_html_e( 'View Terms & Conditions', 'globalpayments-gateway-provider-for-woocommerce' ); ?> →
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="gp-modal-footer">
                    <button class="gp-modal-close-btn" id="gp-modal-close-btn">
                        <?php esc_html_e( 'Close', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </button>
                    <div class="gp-powered-by"><?php esc_html_e( 'Powered by GlobalPayments', 'globalpayments-gateway-provider-for-woocommerce' ); ?></div>
                </div>
            </div>
        </div>
        
        <style>
            /* Trigger Button */
            .gp-installments-trigger {
                margin: 20px 0;
                text-align: center;
            }
            
            .gp-installments-button {
                background: #007cba;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: background-color 0.2s;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            .gp-installments-button:hover {
                background: #005a87;
            }
            
            /* Modal Overlay */
            .gp-modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999999;
                justify-content: center;
                align-items: center;
                padding: 20px;
                box-sizing: border-box;
            }
            
            .gp-modal-overlay.gp-modal-show {
                display: flex;
            }
            
            /* Modal Container */
            .gp-modal-container {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                animation: gp-modal-slide-in 0.3s ease-out;
            }
            
            @keyframes gp-modal-slide-in {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            /* Modal Header */
            .gp-modal-header {
                background: #f8f9fa;
                padding: 20px;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                align-items: center;
                gap: 15px;
                position: relative;
            }
            
            .gp-modal-logo {
                max-height: 40px;
                width: auto;
            }
            
            .gp-modal-header h3 {
                margin: 0;
                color: #333;
                font-size: 20px;
                font-weight: 600;
                flex: 1;
            }
            
            .gp-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                color: #666;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background-color 0.2s;
            }
            
            .gp-modal-close:hover {
                background: #e9ecef;
                color: #333;
            }
            
            /* Modal Content */
            .gp-modal-content {
                padding: 30px;
            }
            
            .gp-installment-summary {
                display: flex;
                gap: 25px;
                align-items: flex-start;
                margin-bottom: 25px;
            }
            
            .gp-installment-badge {
                background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
                color: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                min-width: 120px;
                flex-shrink: 0;
            }
            
            .gp-plan-months {
                display: block;
                font-size: 32px;
                font-weight: bold;
                line-height: 1;
                margin-bottom: 5px;
            }
            
            .gp-plan-label {
                display: block;
                font-size: 12px;
                opacity: 0.9;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .gp-installment-details {
                flex: 1;
            }
            
            .gp-detail-row {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .gp-detail-row:last-child {
                border-bottom: none;
            }
            
            .gp-detail-row.gp-highlight {
                background: rgba(0, 124, 186, 0.1);
                margin: 15px -15px 0;
                padding: 15px;
                border-radius: 6px;
                font-weight: 600;
            }
            
            .gp-label {
                color: #666;
                font-weight: 500;
                font-size: 15px;
            }
            
            .gp-value {
                color: #333;
                font-weight: 600;
                font-size: 15px;
            }
            
            .gp-installment-description {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 6px;
                padding: 20px;
                margin-top: 25px;
                font-size: 14px;
                line-height: 1.5;
                color: #666;
            }
            
            .gp-installment-terms {
                margin-top: 20px;
                text-align: center;
            }
            
            .gp-terms-link {
                display: inline-block;
                color: #007cba !important;
                text-decoration: none;
                font-weight: 500;
                padding: 8px 16px;
                border: 1px solid #007cba;
                border-radius: 4px;
                transition: all 0.3s ease;
            }
            
            .gp-terms-link:hover {
                background-color: #007cba;
                color: white !important;
                text-decoration: none;
            }
            
            /* Modal Footer */
            .gp-modal-footer {
                background: #f8f9fa;
                padding: 20px;
                border-top: 1px solid #e9ecef;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .gp-modal-close-btn {
                background: #007cba;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: background-color 0.2s;
            }
            
            .gp-modal-close-btn:hover {
                background: #005a87;
            }
            
            .gp-powered-by {
                color: #999;
                font-size: 14px;
            }
            
            /* Mobile Responsive */
            @media (max-width: 768px) {
                .gp-modal-container {
                    margin: 10px;
                }
                
                .gp-modal-header,
                .gp-modal-content,
                .gp-modal-footer {
                    padding: 20px;
                }
                
                .gp-installment-summary {
                    flex-direction: column;
                    gap: 20px;
                }
                
                .gp-installment-badge {
                    align-self: center;
                }
                
                .gp-detail-row {
                    flex-direction: column;
                    gap: 5px;
                }
                
                .gp-modal-footer {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }
            }
        </style>
        
        <script type="text/javascript">
            (function() {
                // Auto-show modal after 2 seconds
                setTimeout(function() {
                    var modal = document.getElementById('gp-installments-modal');
                    if (modal) {
                        modal.classList.add('gp-modal-show');
                    }
                }, 2000);
                
                // Modal controls
                var modal = document.getElementById('gp-installments-modal');
                var openBtn = document.getElementById('gp-installments-btn');
                var closeBtn = document.getElementById('gp-modal-close');
                var closeBtnFooter = document.getElementById('gp-modal-close-btn');
                
                // Open modal
                if (openBtn) {
                    openBtn.addEventListener('click', function() {
                        modal.classList.add('gp-modal-show');
                    });
                }
                
                // Close modal functions
                function closeModal() {
                    modal.classList.remove('gp-modal-show');
                }
                
                if (closeBtn) {
                    closeBtn.addEventListener('click', closeModal);
                }
                
                if (closeBtnFooter) {
                    closeBtnFooter.addEventListener('click', closeModal);
                }
                
                // Close modal when clicking overlay
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                }
                
                // Close modal with Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && modal.classList.contains('gp-modal-show')) {
                        closeModal();
                    }
                });
            })();
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render installments information for email
     *
     * @param WC_Order $order
     * @return string
     */
    public static function render_installments_email_info( WC_Order $order ): string {
        $installment_data = self::get_order_installment_data( $order );
        
        if ( ! $installment_data ) {
            return '';
        }
        
        // Extract terms data from nested structure
        $terms_data = $installment_data['terms'] ?? $installment_data;
        
        // Validate required fields exist
        if ( empty( $terms_data['count'] ) ) {
            return '';
        }
        
        ob_start();
        ?>
        <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">
                <?php esc_html_e( 'Installment Plan Details', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
            </h3>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #666; font-weight: 500;">
                        <?php esc_html_e( 'Payment Plan:', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #333; font-weight: 600; text-align: right;">
                        <?php echo esc_html( $terms_data['count'] ); ?> <?php echo esc_html( strtolower( $terms_data['time_unit'] ) ); ?> <?php esc_html_e( 'payments', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #666; font-weight: 500;">
                        <?php esc_html_e( 'Original Amount:', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #333; font-weight: 600; text-align: right;">
                        <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #666; font-weight: 500;">
                        <?php esc_html_e( 'Total Plan Cost:', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #333; font-weight: 600; text-align: right;">
                        <?php echo esc_html( self::format_amount( $terms_data['total_plan_cost'] ?? 0, $terms_data['currency'] ?? 'GBP' ) ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #666; font-weight: 500;">
                        <?php esc_html_e( 'Installment Fees:', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e9ecef; color: #333; font-weight: 600; text-align: right;">
                        <?php echo esc_html( self::format_amount( $terms_data['fees']['total_amount'] ?? 0, $terms_data['currency'] ?? 'GBP' ) ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #666; font-weight: 500;">
                        <?php esc_html_e( 'Interest Rate:', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                    <td style="padding: 8px 0; color: #333; font-weight: 600; text-align: right;">
                        <?php echo esc_html( $terms_data['cost_percentage'] ?? '0' ); ?>% <?php esc_html_e( 'APR', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </td>
                </tr>
            </table>
            
            <?php if ( ! empty( $terms_data['description'] ) ): ?>
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #666; line-height: 1.4;">
                <?php echo esc_html( $terms_data['description'] ); ?>
            </p>
            <?php endif; ?>
            
            <?php if ( ! empty( $terms_data['terms_and_conditions_url'] ) ): ?>
            <p style="margin: 10px 0 0 0; text-align: center;">
                <a href="<?php echo esc_url( $terms_data['terms_and_conditions_url'] ); ?>" style="color: #007cba; text-decoration: none;">
                    <?php esc_html_e( 'View Terms & Conditions', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}