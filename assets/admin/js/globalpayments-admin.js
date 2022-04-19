/* global globalpayments_admin_params */

( function(
    $,
    globalpayments_admin_params
) {
    var digitalWallet = [ 'globalpayments_googlepay', 'globalpayments_applepay' ];

    function GlobalPaymentsAdmin( globalpayments_admin_params ) {
        this.id = globalpayments_admin_params.gateway_id;
        if ( !digitalWallet.includes(globalpayments_admin_params.gateway_id) ) {
            this.toggleCredentialsSettings();
        }
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
