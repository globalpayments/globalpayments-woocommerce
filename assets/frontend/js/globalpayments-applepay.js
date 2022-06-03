( function (
	$,
	globalpayments_applepay_params,
	helper
) {
	function ApplePayWoocommerce ( options ) {
		/**
		 * Payment gateway id
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
		 * Payment gateway options
		 *
		 * @type {object}
		 */
		this.gatewayOptions = options.gateway_options;

		this.attachEventHandlers();
	};

	ApplePayWoocommerce.prototype = {
		/**
		 * Add important event handlers for controlling the payment experience during checkout
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			// General
			$( '#order_review' ).on( 'click', '.payment_methods input.input-radio', helper.toggleSubmitButtons.bind( helper ) );

			// Checkout
			if ( 1 == wc_checkout_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', this.initialize.bind( this ) );
			}
		},

		initialize: function () {
			if ( false === this.deviceSupported() ) {
				$( '.payment_method_' + this.id ).hide();
				return;
			}

			this.addApplePayButton();
		},

		/**
		 * Add the apple pay button to the DOM
		 */
		addApplePayButton: function () {
			helper.createSubmitButtonTarget(this.id);

			var self = this
			var paymentButton = document.createElement( 'div' );
			paymentButton.className = 'apple-pay-button apple-pay-button-white-with-line';
			paymentButton.title = 'Pay with Apple Pay';
			paymentButton.alt = 'Pay with Apple Pay';
			paymentButton.id = self.id;

			paymentButton.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var applePaySession = self.createApplePaySession();
				applePaySession.begin();
			} );

			$( helper.getSubmitButtonTargetSelector( this.id ) ).append( paymentButton );
		},

		createApplePaySession: function () {
			var self = this;
			this.order = helper.order;

			try {
				var applePaySession = new ApplePaySession( 1, self.getPaymentRequest() );
			} catch ( err ) {
				console.error( 'Unable to create ApplePaySession', err )
				alert( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.' );
				return false;
			}

			// Handle validate merchant event
			applePaySession.onvalidatemerchant = function ( event ) {
				self.onApplePayValidateMerchant( event, applePaySession );
			}

			// Attach payment auth event
			applePaySession.onpaymentauthorized = function ( event ) {
				self.onApplePayPaymentAuthorize( event, applePaySession )
			}

			applePaySession.oncancel = function ( event ) {
				alert( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.' );
			}.bind( this );

			return applePaySession;
		},

		onApplePayValidateMerchant: function ( event, session ) {
			$.ajax({
				cache: false,
				url: this.gatewayOptions.validate_merchant_url,
				data: JSON.stringify( { 'validationUrl': event.validationURL } ),
				dataType: 'json',
				type: 'POST'
			} ).done( function ( response ) {
				if ( response.error ) {
					console.log( 'response', response );
					session.abort();
					alert( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.' );
				} else {
					session.completeMerchantValidation( JSON.parse( response.message ) );
				}
			} ).fail( function ( response ) {
				console.log( 'response', response );
				session.abort();
				alert( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.' );
			} );
		},

		onApplePayPaymentAuthorize: function ( event, session ) {
			var paymentToken = JSON.stringify( event.payment.token.paymentData );
			try {
				helper.createInputElement( this.id, 'digital_wallet_token_response', paymentToken );
				var originalSubmit = $( helper.getPlaceOrderButtonSelector() );
				if ( originalSubmit ) {
					originalSubmit.click();
					session.completePayment( ApplePaySession.STATUS_SUCCESS );
					return;
				}
			} catch ( e ) {
				session.completePayment( ApplePaySession.STATUS_FAILURE );
			}
			session.completePayment( ApplePaySession.STATUS_SUCCESS );
			$( this.getForm() ).submit();
		},

		getPaymentRequest: function () {
			return {
				countryCode: this.getCountryId(),
				currencyCode: this.order.currency,
				merchantCapabilities: [
					'supports3DS'
				],
				supportedNetworks: this.getAllowedCardNetworks(),
				total: {
					label: this.getDisplayName(),
					amount: this.order.amount.toString()
				},
			};
		},

		getCountryId: function () {
			return this.gatewayOptions.country_code;
		},

		getDisplayName: function () {
			return this.gatewayOptions.apple_merchant_display_name;
		},

		getAllowedCardNetworks: function () {
			return this.gatewayOptions.cc_types;
		},

		deviceSupported: function () {
			if ( 'https:' !== location.protocol ) {
				console.warn( 'Apple Pay requires your checkout be served over HTTPS' );
				return false;
			}

			if ( true !== ( window.ApplePaySession && ApplePaySession.canMakePayments() ) ) {
				console.warn( 'Apple Pay is not supported on this device/browser' );
				return false;
			}

			return true;
		},
	}

	new ApplePayWoocommerce( globalpayments_applepay_params );
}(
	( window ).jQuery,
	( window ).globalpayments_applepay_params || {},
	( window ).GlobalPaymentsHelper
));
