<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Services\InstallmentsService;
use GlobalPayments\WooCommercePaymentGatewayProvider\Plugin;

use WC_Order;

defined('ABSPATH') || exit;

/**
 * HPP trait
 * 
 * Provides HPP-specific functionality to the GpAPI HPP
 * Handles all the return logic for HPP and signature validation
 *
 * @since 1.14.9
 */
trait HppTrait
{
    /**
     *  Initialize HPP functionality 
     * @return void
     */
    public function init_hpp(): void
    {
        if ($this->is_hpp_mode()) {
            $this->add_hpp_hooks();
            $this->init_installments_hooks();
        }
    }

    /**
     * Add HPP-specific hooks
     */
    protected function add_hpp_hooks(): void
    {
        add_action( 'woocommerce_api_globalpayments_hpp_return', [$this, 'process_hpp_return'] );
        add_action( 'woocommerce_api_globalpayments_hpp_status', [$this, 'process_hpp_status'] );
        add_action( 'woocommerce_api_globalpayments_hpp_cancel', [$this, 'process_hpp_cancel'] );
        add_action( 'woocommerce_api_globalpayments_hpp_final', [$this, 'process_hpp_final'] );
    }



    /**
     * Process HPP payment, called from process_payment from GpApiGateway
     * @param int $order_id
     * @return array Contains HPP URL or error message on failure
     */
    public function process_hpp_payment( int $order_id ): array
    {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp'];
        
        $order = wc_get_order( $order_id );
        
        if (!$order instanceof WC_Order) {
            
            return [
                'result' => 'failure',
                'message' => __('Invalid order', 'globalpayments-gateway-provider-for-woocommerce'),
            ];
        }

        // Validate nonce for security
        if ( !$this->validate_hpp_nonce() ) {
            if ( $this->debug ) {
                $logger->error( 'HPP Payment Processing: Nonce validation failed',  $context );
            }
            
            wc_add_notice(
                __( 'Security check failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ),
                'error'
            );
            return ['result' => 'failure'];
        }

        try {
            // Use standard request-response pattern like other payment methods
            $request = $this->prepare_request( AbstractGateway::TXN_TYPE_CREATE_HPP, $order );
            $gateway_response = $this->submit_hpp_request( $request );
            
            // Extract HPP URL from response
            $hpp_url = $this->extract_hpp_url_from_response( $gateway_response );

            if ( empty( $hpp_url ) ) {
                throw new \Exception('Failed to create HPP URL from gateway response');
            }

            // Add order note
            $note_text = sprintf(
                __( 'HPP payment initiated for %1$s. Transaction ID: %2$s.', 'globalpayments-gateway-provider-for-woocommerce'),
                wc_price( $order->get_total() ),
                $gateway_response->transactionId ?? 'N/A'
            );
            $order->add_order_note( $note_text );

            if ( ! empty( $gateway_response->transactionId ) ) {
                $order->set_transaction_id( $gateway_response->transactionId );
                $order->save();
            }

            return [
                'result' => 'success',
                'redirect' => $hpp_url,
            ];
            
        } catch ( \Exception $e ) {
            if ( $this->debug ) {
                $logger->error( 'HPP Payment Processing: Exception occurred', $context );
            }

            wc_add_notice(
                __( 'Unable to process payment. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ),
                'error'
            );
            return ['result' => 'failure'];
        }
    }

    /**
     * Get provider endpoints for HPP
     *
     * @return array
     */
    public function get_hpp_provider_endpoints(): array {
        return array(
            'returnUrl' => WC()->api_request_url( 'globalpayments_hpp_return', true ),
            'statusUrl' => WC()->api_request_url( 'globalpayments_hpp_status', true ),
            'cancelUrl' => wc_get_checkout_url() . '?cancelled=1',
        );
    }

    /**
     * Submit HPP request 
     *
     * @param mixed $request
     * @return Transaction Containing an payByLinkResponse property with HPP URL
     */
    protected function submit_hpp_request( $request ): Transaction {
        $request->set_request_data( array(
            'globalpayments_hpp' => $this->get_hpp_provider_endpoints(),
        ) );

        $gateway_response = $this->client->submit_request( $request );
        $this->handle_response( $request, $gateway_response );
        
        return $gateway_response;
    }

    /**
     * Extract HPP URL from SDK response
     *
     * @throws \Exception
     * @return string the HPP URL
     */
    protected function extract_hpp_url_from_response( Transaction $response ): string
    {
        if (
            property_exists( $response, 'payByLinkResponse' ) && 
            property_exists( $response->payByLinkResponse, 'url' )
        ) {
            return $response->payByLinkResponse->url;
        }
        
        throw new \Exception( 'HPP URL not found in gateway response' );
    }

    /**
     * Validate HPP nonce from request
     *
     * @return bool True if valid, false otherwise
     */
    protected function validate_hpp_nonce(): bool
    {
        $nonce = $this->get_hpp_nonce_from_request();

        if ( empty( $nonce ) ) {
            return false;
        }

        return wp_verify_nonce( $nonce, 'gp_hpp_payment' );
    }

    /**
     * Extract nonce from POST data
     * @return string containing the nonce empty if not found
     */
    protected function get_hpp_nonce_from_request(): string
    {
        // Check classic 
        if ( isset( $_POST['gp_hpp_nonce'] ) ) {
            return sanitize_text_field( $_POST['gp_hpp_nonce'] );
        }

        // Block checkout
        if ( isset( $_POST['payment_method_data']['gp_hpp_nonce'] ) ) {
            return sanitize_text_field( $_POST['payment_method_data']['gp_hpp_nonce'] );
        }

        return '';
    }

    /**
     * Process HPP return callback
     * wp_die called on signature validation failure
     * @return void
     */
    public function process_hpp_return(): void
    {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp'];
        

        // Get and validate the signature
        $signature = getallheaders()['X-GP-Signature'] ?? '';
        $raw_input = file_get_contents( 'php://input' );


        if ( !$this->validate_hpp_return_signature( $raw_input, $signature ) ) {
            if ( $this->debug ) {
                $logger->error( 'HPP return signature validation failed', $context );
            }
            wp_die( __( 'Invalid signature', 'globalpayments-gateway-provider-for-woocommerce' ), 403 );
            return;
        }

        $input_data = json_decode( $raw_input, true );
        if ( !$input_data ) {
            if ( $this->debug ) {
                $logger->error( 'Failed to parse HPP return data JSON', $context );
            }
            wp_die( __( 'Invalid data', 'globalpayments-gateway-provider-for-woocommerce' ), 400 );
            return;
        }

        // Render the return page 
        $this->render_hpp_return_page( $signature, $raw_input );
    }

    /**
     * Process HPP status callback
     * Not currently implemented
     * @return void
     */
    public function process_hpp_status(): void
    {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp'];
        
        // Handle webhook-style status updates
        wp_die( 'OK', 200 );
    }

    /**
     * Process HPP cancel callback
     * Not currently implemented
     * @return void
     */
    public function process_hpp_cancel(): void
    {
        $logger = wc_get_logger();
        $context = ['source' => 'globalpayments_hpp'];

        wp_redirect( wc_get_checkout_url() . '?cancelled=1' );
        exit;
    }

    /**
     * Process final HPP response before processing the order
     * @return void
     */
    public function process_hpp_final(): void
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'globalpayments_hpp' );
        
        // Validate and sanitize POST data
        if ( !isset( $_POST['X-GP-Signature'] ) || !isset( $_POST['gateway_response'] ) ) {
            if ( $this->debug ) {
                $logger->error( 'Missing required POST data in HPP final', $context );
            }
            wp_die( __( 'Missing required data', 'globalpayments-gateway-provider-for-woocommerce' ), 400 );
        }
    
        $signature = sanitize_text_field( wp_unslash ( $_POST['X-GP-Signature'] ) );
        $gateway_response_json = wp_unslash( $_POST['gateway_response'] );

        
        // Clean the JSON data
        $gateway_response_json = $this->sanitize_hpp_json_input( $gateway_response_json );
        
        if ( !$this->validate_hpp_return_signature( $gateway_response_json, $signature ) ) {
            if ( $this->debug ) {
                $logger->error( 'HPP final signature validation failed', $context );
            }
            wp_die( __( 'Invalid signature', 'globalpayments-gateway-provider-for-woocommerce' ), 403 );
        }

        $gateway_data = json_decode( $gateway_response_json, true );
        if ( !is_array( $gateway_data ) || empty( $gateway_data ) ) {
            if ( $this->debug ) {
                $logger->error( 'HPP Callback Processing: Failed to parse gateway data', $context );
            }
            wp_die( __( 'Invalid response data', 'globalpayments-gateway-provider-for-woocommerce'), 400);
        }

        // Extract order and process the result
        $order_id = $this->extract_order_id_from_response( $gateway_data );
        $order_id = absint( $order_id );
        
        $order = wc_get_order( $order_id );
        
        if ( !$order instanceof WC_Order ) {
            if ( $this->debug ) {
                $logger->error( 'HPP Callback Processing: Order not found',  $context );
            }
            wp_die( __( 'Order not found', 'globalpayments-gateway-provider-for-woocommerce' ), 404 );
        }

        // Process the payment result directly (following AbstractApm pattern)
        $is_successful = $this->is_successful_hpp_response( $gateway_data );
        
        if ( $is_successful ) {
            $transaction_id = $gateway_data['id'] ?? '';
            
            // Handle installments BEFORE payment_complete() to ensure data is available in emails
            if ( $this->has_installments( $gateway_data ) ) {
                $this->save_installment_data( $order, $gateway_data );
            }

            $order->payment_complete( $transaction_id );
            $order->add_order_note(
                sprintf(
                    __( 'Payment completed via HPP. Transaction ID: %s', 'globalpayments-gateway-provider-for-woocommerce' ),
                    $transaction_id
                )
            );

            wp_redirect( $order->get_checkout_order_received_url() );
        } else {
            $error_message = $this->get_error_message_from_response( $gateway_data );

            if ( $this->debug ) {
                $logger->error( 'HPP Callback Processing: Payment failed, updating order status', array_merge( $context, [
                    'error_message' => $error_message,
                ]));
            }
            
            $order->update_status( 'failed', $error_message );
            wc_add_notice( $error_message, 'error' );

           wp_redirect( wc_get_checkout_url() );
        }
        
        exit;
    }
    /**
     * Validate HPP return signature
     * @param string $raw_input JSON input data
     * @param string $signature recived signature
     * @return bool True if valid, false otherwise
     */
    protected function validate_hpp_return_signature( string $raw_input, string $signature ): bool
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'globalpayments_hpp' );
        
        
        if ( empty( $raw_input ) || empty( $signature ) ) {
            if ( $this->debug ) {
                $logger->error( 'HPP: Empty signature or input data', $context );
            }
            return false;
        }

        // Clean escaped characters
        $clean_input = $this->sanitize_hpp_json_input( $raw_input );
        
        // Get the app key from admin settings
        $app_key = $this->get_credential_setting( 'app_key' );
        if ( empty( $app_key ) ) {
            if ( $this->debug ) {
                $logger->error( 'HPP: App key not found', $context );
            }
            return false;
        }

        // Generate expected signature
        $expected_signature = hash( 'sha512', $clean_input . $app_key );

        $signature_match = hash_equals( $expected_signature, $signature );
        
        return $signature_match;
    }

    /**
     * Sanitize JSON input
     *
     * @param string $raw_input
     * @return string cleaned JSON string
     */
    protected function sanitize_hpp_json_input( string $raw_input ): string
    {
        // Check for escaped characters
        if ( false !== strpos( $raw_input, '\"' ) || false !== strpos( $raw_input, '\\' ) ) {
            
            $replacements = [
                '\"' => '"',
                '\\/' => '/',
                '\\\\' => '\\'
            ];

            $clean_input = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw_input );

            return $clean_input;
        }
        
        return $raw_input;
    }

    /**
     * Render the HPP return page with auto-submit form
     */
    protected function render_hpp_return_page( string $signature, string $input_data ): void
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'globalpayments_hpp' );

        // Sanitize input data
        $signature = sanitize_text_field( $signature );
        $input_data = $this->sanitize_hpp_json_input( $input_data );

        
        // Prepare view data for the template
        $final_url = WC()->api_request_url( 'globalpayments_hpp_final' );

        // Validate signature
        $signature_valid = $this->validate_hpp_return_signature( $input_data, $signature );

        // Parse JSON data to extract values for the view
        $parsed_input_data = json_decode( $input_data, true );

        // Calculate transaction outcome
        $transaction_outcome = 'FAILED'; // Default to failed
        if ( is_array( $parsed_input_data ) && ! empty( $parsed_input_data ) ) {
            $transaction_outcome = $this->is_successful_hpp_response( $parsed_input_data ) ? 'SUCCESS' : 'FAILED';
        }
        
        // Extract order ID
        $order_id = '';
        if ( isset( $parsed_input_data['link_data']['reference'] ) ) {
            $store_name = get_bloginfo( 'name' );
            $reference = sanitize_text_field( $parsed_input_data['link_data']['reference'] );
            $order_id = str_replace( $store_name . " Order #", "", $reference );
            $order_id = absint( $order_id );
        }
        
        // Extract transaction ID
        $transaction_id = sanitize_text_field( $parsed_input_data['id'] ?? '' );

        // Extract error message
        $error_message = ! empty( $parsed_input_data['payment_method']['message'] )
            ? sanitize_text_field( $parsed_input_data['payment_method']['message'] )
            : __( 'Unfortunately, your payment could not be processed.', 'globalpayments-gateway-provider-for-woocommerce' );


        // Prepare template data
        $template_args = array(
            'gateway_response' => $input_data,
            'signature_valid' => $signature_valid,
            'gp_signature' => $signature,
            'final_url' => $final_url,
            'transaction_outcome' => $transaction_outcome,
            'order_id' => $order_id,
            'transaction_id' => $transaction_id,
            'error_message' => $error_message,
        );
        

        // Load the template
        $template_file = Plugin::get_path() . '/includes/frontend/views/HPPReturn.php';
        if ( file_exists( $template_file ) ) {
            extract( $template_args );
            include $template_file;
        } else {
            // Fallback rendering if template not found
            $this->render_hpp_return_fallback( $signature, $input_data );
        }
        
        exit;
    }
    
    /**
     * Fallback HTML for HPP return page
     *
     * @param string $signature received signature
     * @param string $input_data_json JSON input data
     * @return void
     */
    protected function render_hpp_return_fallback( string $signature, string $input_data_json ): void
    {
        $final_url = WC()->api_request_url( 'globalpayments_hpp_final' );

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php esc_html_e( 'Processing Payment...', 'globalpayments-gateway-provider-for-woocommerce' ); ?></title>
        </head>
        <body>
            <h1><?php esc_html_e( 'Processing your payment...', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h1>
            <script>
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "<?php echo esc_url($final_url); ?>";

                const signatureInput = document.createElement("input");
                signatureInput.type = "hidden";
                signatureInput.name = "X-GP-Signature";
                signatureInput.value = <?php echo wp_json_encode($signature); ?>;
                form.appendChild(signatureInput);

                const responseInput = document.createElement("input");
                responseInput.type = "hidden";
                responseInput.name = "gateway_response";
                responseInput.value = <?php echo wp_json_encode($input_data_json); ?>;
                form.appendChild(responseInput);

                document.body.appendChild(form);
                form.submit();
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Extract order ID from gateway response
     *
     * @param array $gateway_data
     * @return int
     */
    protected function extract_order_id_from_response( array $gateway_data ): int
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'globalpayments_hpp' );
        
        // Try to extract from reference field
        $reference = $gateway_data['link_data']['reference'] ?? '';

        if ( preg_match( '/Order #(\d+)/', $reference, $matches) ) {
            $order_id = (int) $matches[1];
            
            return $order_id;
        }

        // Fallback, try to get from GET parameters
        $fallback_order_id = (int) ($_GET['order_id'] ?? 0);
        
        return $fallback_order_id;
    }

    /**
     * Determine if HPP payment was successful from gateway response
     *
     * @param array $gateway_data
     * @return bool true if successful, false otherwise
     */
    protected function is_successful_hpp_response( array $gateway_data ): bool
    {
        $status = $gateway_data['status'] ?? '';
        $result_code = $gateway_data['payment_method']['result'] ?? '';
        $action_result = $gateway_data['action']['result_code'] ?? '';
        
        $is_successful = $status === 'CAPTURED' && $result_code === '00' && $action_result === 'SUCCESS';
        
        return $is_successful;
    }

    /**
     * Get error message from gateway response
     *
     * @param array $gateway_data
     * @return string containing error message
     */
    protected function get_error_message_from_response( array $gateway_data ): string
    {
        return $gateway_data['payment_method']['message'] ?? 
               __('Payment failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce');
    }

    /**
     * Check if payment includeed installments data
     *
     * @param array $gateway_data
     * @return bool true if installments data present, false otherwise
     */
    protected function has_installments( array $gateway_data ): bool
    {
        return !empty( $gateway_data['installment'] ) && !empty( $gateway_data['installment']['terms'] );
    }

    /**
     * Save installment data to order
     *
     * @param WC_Order $order
     * @param array $gateway_data
     * @return void
     */
    protected function save_installment_data( WC_Order $order, array $gateway_data ): void
    {
        $installment_data = $gateway_data['installment'] ?? [];
        
        if (!empty($installment_data)) {
            $order->update_meta_data( '_globalpayments_installment_data', $installment_data );
            $order->update_meta_data( '_gp_has_installments', 'yes' );
            $order->save();
        }
    }

    /**
     * Initialize installments hooks for HPP
     */
    protected function init_installments_hooks(): void
    {
        // Only initialize if payment interface is HPP
        if ( !$this->is_hpp_mode() ) {
            return;
        }
        
        // Add installments info to order success page
        add_action( 'woocommerce_thankyou_' . $this->id, [$this, 'display_installments_on_success_page'], 2, 1 );
        
        // Add installments info to customer emails
        add_action( 'woocommerce_email_after_order_table', [$this, 'add_installments_to_email'], 20, 4 );
    }
    
    /**
     * Display installments information on order success page 
     * Only for HPP payments
     * @param $order_id
     * @return void
     *
     * @param int $order_id
     */
    public function display_installments_on_success_page( int $order_id ): void
    {
        if ( !$order_id ) {
            return;
        }
        
        $order = wc_get_order( $order_id );
        if ( !$order || $order->get_payment_method() !== $this->id ) {
            return;
        }
        
        // Additional check to ensure this is HPP mode
        if ( !$this->is_hpp_mode() ) {
            return;
        }

        if ( InstallmentsService::order_has_installments( $order ) ) {
            // Render installments information
            echo InstallmentsService::render_installments_info( $order );
        }
    }
    
    /**
     * Add installments information to customer emails (HPP only)
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param WC_Email $email
     */
    public function add_installments_to_email(
        \WC_Order $order,
        bool $sent_to_admin,
        bool $plain_text,
        \WC_Email $email
    ): void
    {
        // Only show for customer emails, not admin emails
        if ( $sent_to_admin ) {
            return;
        }
        
        if ( $order->get_payment_method() !== $this->id ) {
            return;
        }
        
        if ( !$this->is_hpp_mode() ) {
            return;
        }

        // Check order has been paid
        if ( !$order->is_paid() ) {
            return;
        }
        
        // Only show for order emails where payment was successful
        $allowed_email_ids = [
            'customer_processing_order',
            'customer_completed_order'
        ];
        
        if ( !in_array( $email->id, $allowed_email_ids ) ) {
            return;
        }
        
        // Check if order has installments data returned from external gateway
        if ( InstallmentsService::order_has_installments( $order ) ) {

            if ( $plain_text ) {
                InstallmentsService::render_installments_info_plaintext( $order );
            } else {
                echo InstallmentsService::render_installments_email_info( $order );
            }
        }
    }
}
