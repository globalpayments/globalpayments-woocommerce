/* global globalpayments_admin_params */

( function(
    $,
    globalpayments_admin_params
) {
    function GlobalPaymentsAdmin( globalpayments_admin_params ) {
        this.id = globalpayments_admin_params.gateway_id;
        this.toggleCredentialsSettings();
        this.attachEventHandlers();
    };
    GlobalPaymentsAdmin.prototype = {
        /**
         * Add important event handlers
         *
         * @returns
         */
        attachEventHandlers: function () {
            $( document ).on( 'change', this.getLiveModeSelector(), this.toggleCredentialsSettings.bind( this ) );

            // Admin Pay for Order
            $( '#customer_user' ).on( 'change', this.updatePaymentMethods );
            $( '.wc-globalpayments-pay-order' ).on( 'click', this.payForOrder );
            $( document.body ).on('wc_backbone_modal_loaded', this.modalLoaded.bind( this ) );
        },

        updatePaymentMethods: function ( e ) {
            // fetch user payment tokens
            var customer_id = $( e.target ).val();
            globalpayments_admin_params.payment_methods = [];
            if ( customer_id > 0 && typeof globalpayments_admin_params !== "undefined" ) {
                var data = {
                    _wpnonce: globalpayments_admin_params._wpnonce,
                    customer_id: customer_id
                };
                $( '.wc-globalpayments-pay-order' ).prop( 'disabled', true );
                $.get( globalpayments_admin_params.payment_methods_url, data, function ( response ) {
                    globalpayments_admin_params.payment_methods = response;
                    $( '.wc-globalpayments-pay-order' ).prop( 'disabled', false );
                }, 'json' );
            }
        },

        /**
         * Enable modal template.
         *
         * @param e
         */
        payForOrder: function( e ) {
            e.preventDefault();
            $( this ).WCGlobalPaymentsPayOrderBackboneModal({
                template: 'wc-globalpayments-pay-order-modal',
                variable: {
                    customer_id: $( '#customer_user' ).val(),
                    payment_methods: globalpayments_admin_params.payment_methods,
                }
            });
        },

        /**
         * Render modal content.
         *
         * @param e
         * @param target
         */
        modalLoaded: function ( e, target ) {
            switch ( target ) {
                case 'wc-globalpayments-pay-order-modal':
                    $( document.body ).trigger( 'globalpayments_pay_order_modal_loaded' );
                    $( document.body ).trigger( 'wc-credit-card-form-init' );
                    break;
            }
        },

        /**
         * Checks if "Live Mode" setting is enabled
         *
         * @returns {*|jQuery}
         */
        isLiveMode: function() {
            return $( this.getLiveModeSelector() ).is( ':checked' );
        },

        /**
         * Toggle gateway credentials settings
         */
        toggleCredentialsSettings: function () {
            var globalpayments_keys = {
                globalpayments_gpapi: [
                  'app_id',
                  'app_key',
                ],
                globalpayments_heartland: [
                    'public_key',
                    'secret_key',
                ],
                globalpayments_genius: [
                    'merchant_name',
                    'merchant_site_id',
                    'merchant_key',
                    'web_api_key',
                ],
                globalpayments_transit: [
                    'merchant_id',
                    'user_id',
                    'password',
                    'device_id',
                    'tsep_device_id',
                    'transaction_key',
                ],
            };
            var gateway_credentials = globalpayments_keys[ this.id ];
            if ( this.isLiveMode() ) {
                gateway_credentials.forEach( function( key ) {
                    $( '#woocommerce_' + this.id + '_' + key ).parents( 'tr' ).eq( 0 ).show();
                    $( '#woocommerce_' + this.id + '_sandbox_' + key ).parents( 'tr' ).eq( 0 ).hide();
                }, this );
            } else {
                gateway_credentials.forEach(function(key) {
                    $( '#woocommerce_' + this.id + '_' + key ).parents( 'tr' ).eq( 0 ).hide();
                    $( '#woocommerce_' + this.id + '_sandbox_' + key ).parents( 'tr' ).eq( 0 ).show();
                }, this );
            }
        },

        /**
         * Convenience function to get CSS selector for the "Live Mode" setting
         *
         * @returns {string}
         */
        getLiveModeSelector: function () {
            return '#woocommerce_' + this.id + '_is_production';
        }
    };
    new GlobalPaymentsAdmin( globalpayments_admin_params );
}(
    /**
     * Global `jQuery` reference
     *
     * @type {any}
     */
    (window).jQuery,
    /**
     * Global `wc_checkout_params` reference
     *
     * @type {any}
     */
    (window).globalpayments_admin_params || {},
));
