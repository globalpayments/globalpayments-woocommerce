<?php
/**
 * HPP Return Page Template 
 *
 * This is rendered on the external Payment Page, displays the transaction outcome,
 * before redirecting back to the the final HPP endpoint with the tranaction data
 * and signature for order completion.
 * 
 * @var string $gateway_response JSON response data
 * @var bool $signature_valid Signature validation result
 * @var string $gp_signature GlobalPayments signature to post to final endpoint
 * @var string $final_url Final processing URL
 * @var string $transaction_outcome 'SUCCESS' or 'FAILED'
 * @var int $order_id WooCommerce order ID, added as GET param
 * @var string $transaction_id Transaction ID
 * @var string $error_message Error message (if any)
 * 
 * @since 1.14.9
 */

/**
 * Note: Signature verification is handled by the  HPPTrait before this template is rendered.
 * Failed tranactions or invalid signatures will display an error message and redirect back to 
 * WooCommerce checkout.
 */

defined( 'ABSPATH' ) || exit;

// Parse the JSON data for use in the template
$payment_data = json_decode($gateway_response, true);

// Global Payments logo URL
$gp_logo_url = \GlobalPayments\WooCommercePaymentGatewayProvider\Plugin::get_url( '/assets/frontend/img/globalpayments-logo.png' ) ?? "";
// Store name
$store_name = get_bloginfo( 'name' );
// Store logo URL
$store_logo_id = get_theme_mod( 'custom_logo' );
$store_logo_url = wp_get_attachment_image_src( $store_logo_id, 'full' )[0] ?? '';

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $transaction_outcome === 'SUCCESS' ? esc_html__( 'Payment Successful', 'globalpayments-gateway-provider-for-woocommerce' ) : esc_html__( 'Payment Error', 'globalpayments-gateway-provider-for-woocommerce' ); ?></title>
    <!-- Minimal CSS Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 500px;
        }
        
        .container::before {
            content: "";
            background-color: #fff;
            position: absolute;
            width: 100vw;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: -1;
        }

        .header {
            margin-bottom: 2rem;
        }
        
        .logo {
            max-height: 50px;
            margin: 0 1rem 1rem;
        }
        
        .store-name {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 1rem;
        }
        
        .status-icon {
            font-size: 4rem;
            margin: 1rem 0;
            font-weight: bold;
        }
        
        .status-icon.success {
            color: #28a745;
        }
        
        .status-icon.error {
            color: #dc3545;
        }
        
        h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .order-row:last-child {
            margin-bottom: 0;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .order-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-value {
            color: #333;
            font-weight: 500;
        }
        
        .countdown {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            font-style: italic;
        }
        
        .processing-message {
            color: #0073aa;
            font-weight: 600;
            margin-top: 15px;
        }
                
        .footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .powered-by {
            color: #999;
            font-size: 0.875rem;
        }
        
        .redirect-message {
            color: #666;
            font-style: italic;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .continue-button {
            background: #007cba;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 1rem;
        }
        
        .continue-button:hover {
            background: #005a87;
        }
        
        .continue-button.error-button {
            background: #dc3545;
        }
        
        .continue-button.error-button:hover {
            background: #c82333;
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 2rem 1.5rem;
            }
            
            .status-icon {
                font-size: 3rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .order-row {
                flex-direction: column;
                gap: 2px;
            }
        }
    </style>
</head>
<body <?php body_class( 'globalpayments-hpp-return' ); ?>>
    <div class="container">
        <div class="header">
            <!-- Store Logo and Name if available -->
            <?php if ( ! empty( $store_logo_url ) ): ?>
                <img src="<?php echo esc_url( $store_logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?>" class="logo">
            <?php else: ?>
                <h1 class="store-name"><?php echo esc_html( $store_name ); ?></h1>
            <?php endif; ?>
            <?php if ( ! empty( $gp_logo_url ) ): ?>
                <img src="<?php echo esc_url( $gp_logo_url ); ?>" alt="<?php esc_attr_e( 'GlobalPayments', 'globalpayments-gateway-provider-for-woocommerce' ); ?>" class="logo">
            <?php endif; ?>
        </div>
        
        <div class="content">
            <?php if ( $transaction_outcome === 'SUCCESS' && $signature_valid ): ?>
                <!-- Successful payment message -->
                <div class="status-icon success">&#x2714;</div>
                <h2><?php esc_html_e( 'Payment Successful', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h2>
                <p><?php esc_html_e( 'Your payment has been processed successfully.', 'globalpayments-gateway-provider-for-woocommerce' ); ?></p>
                <p class="countdown">
                    <?php echo esc_html__('Redirecting to order confirmation in', 'globalpayments-gateway-provider-for-woocommerce'); ?> 
                    <span id="countdown">5</span> 
                    <?php echo esc_html__('seconds...', 'globalpayments-gateway-provider-for-woocommerce'); ?>
                </p>
                
                <?php if ( isset( $order_id ) && $order_id ): ?>
                <div class="order-summary">
                    <div class="order-row">
                        <span class="order-label"><?php esc_html_e( 'Order Number:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                        <span class="order-value">#<?php echo esc_html( $order_id ); ?></span>
                    </div>
                    
                    <?php if ( ! empty( $transaction_id ) ): ?>
                        <!-- TODO: check this value, it appears to be very long and outside of the main container -->
                    <div class="order-row">
                        <span class="order-label"><?php esc_html_e( 'Transaction ID:', 'globalpayments-gateway-provider-for-woocommerce' ); ?></span>
                        <span class="order-value"><?php echo esc_html( $transaction_id ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div id="processing-message" class="processing-message" style="display: none;">
                    <?php echo esc_html__('Processing...', 'globalpayments-gateway-provider-for-woocommerce'); ?>
                </div>
            <?php else: ?>
                <!-- Payment error message -->
                <div class="status-icon error">&#x2716;</div>
                <h2><?php esc_html_e( 'Payment Error', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h2>
                <?php if ( ! $signature_valid ): ?>
                    <p><?php esc_html_e( 'Payment verification failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ); ?></p>
                <?php else: ?>
                    <p><?php echo esc_html( $error_message ); ?></p>
                <?php endif; ?>
                <p class="countdown">
                    <?php echo esc_html__('Redirecting to checkout in', 'globalpayments-gateway-provider-for-woocommerce'); ?> 
                    <span id="countdown">3</span> 
                    <?php echo esc_html__('seconds...', 'globalpayments-gateway-provider-for-woocommerce'); ?>
                </p>
            <?php endif; ?>
        </div>
        <!-- Footer Text -->
        <div class="footer">
            <div class="powered-by"><?php esc_html_e( 'Powered by GlobalPayments', 'globalpayments-gateway-provider-for-woocommerce' ); ?></div>
        </div>
    </div>

    <?php if ($transaction_outcome === 'SUCCESS' && $signature_valid && isset($order_id)): ?>
        <!--
         HPP Final Form
        Posts the tranaction data back to the final endpoint for order completion
        along with the same signature, so It can be verified again at the final endpoint
        -->
    <form id="hpp-final-form" method="POST" action="<?php echo esc_url( $final_url . '?order_id=' . $order_id ); ?>" style="display: none;">
        <input type="hidden" name="gateway_response" value="<?php echo esc_attr( $gateway_response ); ?>">
        <input type="hidden" name="X-GP-Signature" value="<?php echo esc_attr( $gp_signature ?? '' ); ?>">
    </form>
    <?php endif; ?>

        <!-- JavaScript for countdown, redirection and form submission -->
    <script type="text/javascript">
            (function() {
            <?php if ($transaction_outcome === 'SUCCESS' && $signature_valid): ?>
            // Success page with valid signature - countdown and submit to final endpoint
            let countdown = 5;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(function() {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    const processingMessage = document.getElementById('processing-message');
                    if (processingMessage) {
                        processingMessage.style.display = 'block';
                    }
                    
                    // Submit the form to final endpoint
                    const form = document.getElementById('hpp-final-form');
                    if (form) {
                        form.submit();
                    }
                }
            }, 1000);
            
            <?php else: ?>
            // Failed transaction or invalid signature - countdown and redirect to checkout
            let countdown = 3;
            const countdownElement = document.getElementById('countdown');
            
            const timer = setInterval(function() {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = '<?php echo esc_url( wc_get_checkout_url() ); ?>';
                }
            }, 1000);
            <?php endif; ?>
        })();
    </script>
</body>
</html>