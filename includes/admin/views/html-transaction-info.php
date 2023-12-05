<p class="form-field form-field-wide">
    <button class="button button-secondary wc-globalpayments-transaction-info">
        <?php esc_html_e( 'View Transaction Info', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
    </button>
    <?php echo wc_help_tip( __( 'Admins can view transaction details using this functionality.', 'globalpayments-gateway-provider-for-woocommerce' ) ); ?>
</p>
<script type="text/template" id="tmpl-wc-globalpayments-transaction-info-modal">
    <div class="wc-backbone-modal">
        <div class="wc-backbone-modal-content wc-transaction-info">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1>
                        <?php esc_html_e( 'View Transaction Info', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                    </h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                   <?php wp_nonce_field( 'woocommerce-globalpayments-view-transaction-info', 'woocommerce-globalpayments-view-transaction-info-nonce' ); ?>
                    <#if( data.error_message ){#>
                        <div class="wc-globalpayments-transaction-info-error">
                            {{ data.error_message }}
                        </div>
                    <#}else{#>
                    <div class="container">
                        <h2>
                            <?php esc_html_e( 'General Info', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                        </h2>
                        <#if( Object.keys(data.transaction_info).length ){#>
                            <table>
                                <tr>
                                    <td>
                                        <?php esc_html_e( 'Transaction Id', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                    </td>
                                    <td>
                                        {{ data.transaction_info.transaction_id }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php esc_html_e( 'Transaction Status', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                    </td>
                                    <td>
                                        {{ data.transaction_info.transaction_status }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php esc_html_e( 'Transaction Type', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                    </td>
                                    <td>
                                        {{ data.transaction_info.transaction_type }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php esc_html_e( 'Amount', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                    </td>
                                    <td>
                                        {{ data.transaction_info.amount }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <?php esc_html_e( 'Currency', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                    </td>
                                    <td>
                                        {{ data.transaction_info.currency }}
                                    </td>
                                </tr>
                                <#if( data.transaction_info.provider_type ){#>
                                    <tr>
                                        <td>
                                            {{ data.transaction_info.provider }} <?php esc_html_e( 'Provider', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                        </td>
                                        <td>
                                            {{ data.transaction_info.provider_type }}
                                        </td>
                                    </tr>
                                <#}#>
                                <#if( data.transaction_info.payment_type ){#>
                                    <tr>
                                        <td>
                                            <?php esc_html_e( 'Payment Type', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                                        </td>
                                        <td>
                                            {{ data.transaction_info.payment_type }}
                                        </td>
                                    </tr>
                                <#}#>
                            </table>
                        <#}#>
                    </div>
                    <#}#>
                </article>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
