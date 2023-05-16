( function (
	$,
	globalpayments_clicktopay_params,
	helper
) {
	function ClickToPayWoocommerce ( options ) {
		/**
		 * Click To Pay form instance
		 *
		 * @type {any}
		 */
		this.ctpForm = {};

		/**
		 * Payment method id
		 *
		 * @type {string}
		 */
		this.id = options.id;

		/**
		 * The current order
		 *
		 * @type {object}
		 */
		this.order = {};

		/**
		 * Payment method options
		 *
		 * @type {object}
		 */
		this.paymentMethodOptions = options.payment_method_options;

		this.attachEventHandlers();
	};

	ClickToPayWoocommerce.prototype = {
		/**
		 * Add important event handlers for controlling the payment experience during checkout
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			var self = this;
			// General
			$( '#order_review' ).on( 'click', '.payment_methods input.input-radio', helper.toggleSubmitButtons.bind( helper ) );
			$( helper.getForm() ).on( 'checkout_place_order_' + this.id, function() {
				if ( undefined !== $('#' + self.id + '-dw_token').val() ) {
					return true;
				}
				return false;
			} );
			$( document.body ).on( 'checkout_error', this.renderClickToPay.bind( this ) );

			// Checkout
			if ( 1 == wc_checkout_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', this.renderClickToPay.bind( this ) );
				return;
			}

			// Order Pay
			if ( $( document.body ).hasClass( 'woocommerce-order-pay' ) ) {
				$( document ).ready( this.renderClickToPay.bind( this ) );
				return;
			}
		},

		/**
		 * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
		 * Global Payments' production servers.
		 *
		 * @returns
		 */
		renderClickToPay: function () {
			this.clearContent();

			if ( ! GlobalPayments.configure ) {
				console.log( 'Warning! Payment fields cannot be loaded' );
				return;
			}

			var gatewayConfig = this.paymentMethodOptions;
			if ( gatewayConfig.error ) {
				console.error( gatewayConfig.message );
				helper.hidePaymentMethod( this.id );
				return;
			}

			this.order = helper.order;
			gatewayConfig.apms.currencyCode = this.order.currency;

			GlobalPayments.configure( gatewayConfig );
			GlobalPayments.on( 'error', this.handleErrors.bind( this ) );

			this.ctpForm = GlobalPayments.apm.form( '#' + this.id, {
					amount: this.order.amount.toString(),
					style: "gp-default",
					apms: [ GlobalPayments.enums.Apm.ClickToPay ]
			} );

			this.ctpForm.on( 'token-success', this.handleResponse.bind( this ) );

			this.ctpForm.on( 'token-error', this.handleErrors.bind( this ) );
			this.ctpForm.on( 'error', this.handleErrors.bind( this ) );
		},

		/**
		 * If the CTP element already has some previous content, clear it.
		 */
		clearContent: function() {
			var ctpElement = $( '#' + this.id );
			if ( ctpElement.children().length > 0 ) {
				ctpElement.empty();
				$( '#' + this.id + '-dw_token' ).remove();
			}
		},

		/**
		 * Handles the tokenization response
		 *
		 * On valid payment fields, the tokenization response is added to the current
		 * state, and the order is placed.
		 *
		 * @param {object} response tokenization response
		 *
		 * @returns
		 */
		handleResponse: function ( response ) {
			var self = this;

			helper.createInputElement(
				self.id,
				'dw_token',
				response.paymentReference
			)

			return helper.placeOrder();
		},

		/**
		 * Handles errors from the payment field
		 *
		 * @param {object} error Details about the error
		 *
		 * @returns
		 */
		handleErrors: function ( error ) {
			console.error( error );
		},
	}

	new ClickToPayWoocommerce( globalpayments_clicktopay_params );
}(
	( window ).jQuery,
	( window ).globalpayments_clicktopay_params || {},
	( window ).GlobalPaymentsHelper
) );
