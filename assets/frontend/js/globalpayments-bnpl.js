( function ( $ ) {
	function GlobalPaymentsBNPLWooCommerce() {
		this.attachEventHandlers();
	};

	GlobalPaymentsBNPLWooCommerce.prototype = {
		/**
		 * Add important event handlers for controlling the payment experience during checkout
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			// Fix `Checkout` and `Order Pay` pages after back button
			$( window ).on( 'pageshow' , this.unblockForm.bind( this ) );
		},

		/**
		 * Unblock the form on `Checkout` and `Order Pay` pages after back button
		 *
		 * @param e
		 */
		unblockForm: function ( e ) {
			if ( e.originalEvent.persisted ) {
				var $form = $( this.getForm() );
				$( document.body ).on( 'wc_fragments_refreshed wc_fragments_ajax_error', function () {
					if ( $form.is( '.processing' ) ) {
						$form.removeClass( 'processing' ).unblock();
					}
				} );
			}
		},

		/**
		 * Gets the current checkout form
		 *
		 * @returns {Element}
		 */
		getForm: function () {
			var checkoutForms = [
				// Order Pay
				'form#order_review',
				// Checkout
				'form[name="checkout"]',
			];
			var forms = document.querySelectorAll( checkoutForms.join( ',' ) );

			return forms.item( 0 );
		},
	};

	if ( ! window.GlobalPaymentsBNPLWooCommerce ) {
		window.GlobalPaymentsBNPLWooCommerce = new GlobalPaymentsBNPLWooCommerce();
	}
} (
	( window ).jQuery
) );
