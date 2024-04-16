( function(
    $,
    single_toggle_gateways
) {
    const { __, sprintf } = wp.i18n;

    $( function() {
        // Toggle GlobalPayments gateway on/off.
        $( '[data-gateway_id^="globalpayments_"]' ).on( 'click', '.wc-payment-gateway-method-toggle-enabled', function() {
            var toggle = true;

            if ( ! single_toggle_gateways.includes( $( this ).closest( 'tr' ).data( 'gateway_id' ) ) ) {
                return;
            }

            // Toggle off
            if ( ! $( this ).find( 'span.woocommerce-input-toggle--disabled' ).length > 0 ) {
                return true;
            }

            // Toggle on
            var clicked = $( this ).closest( 'tr' ).data( 'gateway_id' );
            $( '[data-gateway_id^="globalpayments_"]' ).each( function() {
                if ( ! $( this ).find( 'span.woocommerce-input-toggle--disabled' ).length > 0 ) {
                    if ( single_toggle_gateways.includes( $( this ).data( 'gateway_id' ) ) && single_toggle_gateways.includes( clicked ) ) {
                        var gateway_title = $( this ).closest( 'tr' ).find( 'a.wc-payment-gateway-method-title' ).text();
                        window.alert(
                            sprintf(
                                __( 'You can enable only one GlobalPayments gateway at a time. Please disable %s first!', 'globalpayments-gateway-provider-for-woocommerce' ),
                                gateway_title
                            )
                        );
                        toggle = false;
                        return;
                    }
                }
            });

            return toggle;
        });
    });

})(
    (window).jQuery,
    (window).globalpayments_enforce_single_gateway_params || []
);
