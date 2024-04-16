<p class="form-field form-field-wide">
    <button class="button button-secondary wc-globalpayments-pay-order"><?php esc_html_e( 'Pay for Order', 'globalpayments-gateway-provider-for-woocommerce' ); ?></button>
    <?php echo wc_help_tip( __( 'Admins can process customer orders over the phone using this functionality.', 'globalpayments-gateway-provider-for-woocommerce' ) ); ?>
</p>
<script type="text/template" id="tmpl-wc-globalpayments-pay-order-modal">
    <div class="wc-backbone-modal">
        <div class="wc-backbone-modal-content">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php esc_html_e( 'Pay for Order', 'globalpayments-gateway-provider-for-woocommerce' ); ?></h1>
                    <button
                            class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                    <form id="wc-globalpayments-pay-order-form">
                        <?php wp_nonce_field( 'woocommerce-globalpayments-pay', 'woocommerce-globalpayments-pay-nonce' ); ?>
                        <input type="hidden" name="woocommerce_globalpayments_pay" value="1"/>
                        <input type="hidden" name="entry_mode"
                               value="<?php echo \GlobalPayments\Api\Entities\Enums\ManualEntryMethod::MOTO; ?>"/>
                        <input type="hidden" name="order_id" value="<?php echo $order->get_id(); ?>"/>
                        <input type="hidden" name="order_key" value="<?php echo $order->get_order_key(); ?>"/>
                        <div class="payment_methods" style="display: none">
                            <input type="radio" class="input-radio" name="payment_method"
                                   value="<?php echo $this->id; ?>" checked="checked">
                        </div>
                        <?php $this->environment_indicator(); ?>
                        <div class="wc_payment_method payment_method_<?php echo $this->id; ?> payment_box">
                            <ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods"
                                data-count="{{ data.payment_methods.length }}">
                                <#if( data.payment_methods.length ){#>
                                    <#_.each( data.payment_methods, function( method ){#>
                                    <li class="woocommerce-SavedPaymentMethods-token">
                                        <input id="wc-<?php echo $this->id; ?>-payment-token-{{ method.id }}"
                                               type="radio"
                                               name="wc-<?php echo $this->id; ?>-payment-token"
                                               value="{{ method.id }}"
                                               style="width:auto;"
                                               class="woocommerce-SavedPaymentMethods-tokenInput"<#if( method.is_default
                                        ){#> checked="checked"<#}#>/>
                                        <label for="wc-<?php echo $this->id; ?>-payment-token-{{ method.id }}">{{
                                            method.display_name }}</label>
                                    </li>
                                    <#})#>
                                <#}#>
                                <?php
                                echo $this->get_new_payment_method_option_html();
                                $this->form();
                                ?>
                            </ul>
                        </div>
                        <?php if ( $order->get_transaction_id() ) : ?>
                            <fieldset id="wc-globalpayments_gpapi-txn-form">
                                <p><?php esc_html_e( 'This order has a transaction ID associated with it already. Click the checkbox to proceed.', 'globalpayments-gateway-provider-for-woocommerce' ); ?></p>
                                <input type="hidden" name="transaction_id"
                                       value="<?php echo $order->get_transaction_id(); ?>"/>
                                <input type="checkbox" name="allow_order"/>
                                <label><?php esc_html_e( 'Ok to process order', 'globalpayments-gateway-provider-for-woocommerce' ); ?></label>
                            </fieldset>
                        <?php endif; ?>
                    </form>
                </article>
                <footer>
                    <div class="inner">
                        <button type="submit" class="button button-primary button-large" id="place_order" value="Pay" data-value="<?php esc_html_e( 'Pay', 'globalpayments-gateway-provider-for-woocommerce' ); ?>">
	                        <?php esc_html_e( 'Pay', 'globalpayments-gateway-provider-for-woocommerce' ); ?>
                        </button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>
