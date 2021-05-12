// @ts-check

(function (
	$,
	wc_checkout_params,
	GlobalPayments,
	globalpayments_secure_payment_fields_params
) {
	/**
	 * Frontend code for Global Payments in WooCommerce
	 *
	 * @param {object} options
	 */
	function GlobalPaymentsWooCommerce(options) {

		/**
		 * Card form instance
		 *
		 * @type {any}
		 */
		this.cardForm = {};

		/**
		 * Payment gateway id
		 *
		 * @type {string}
		 */
		this.id = options.id;
		/**
		 * Payment field options
		 *
		 * @type {object}
		 */
		this.fieldOptions = options.field_options;
		/**
		 * Payment gateway options
		 *
		 * @type {object}
		 */
		this.gatewayOptions = options.gateway_options;
		this.attachEventHandlers();
	};

	GlobalPaymentsWooCommerce.prototype = {
		/**
		 * Add important event handlers for controlling the payment experience during checkout
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			var that = this;

			// General
			$( '#order_review, #add_payment_method' ).on( 'click', '.payment_methods input.input-radio', this.toggleSubmitButtons.bind( this ) );

			// Saved payment methods
			$( document.body ).on(
				'updated_checkout wc-credit-card-form-init',
				function () {
					$( '.payment_method_' + that.id + ' .wc-saved-payment-methods' ).on( 'change', ':input.woocommerce-SavedPaymentMethods-tokenInput', that.toggleSubmitButtons.bind( that ) );
				}
			);

			// Order Pay + Add payment method
			if ( $( document.body ).hasClass( 'woocommerce-order-pay' ) || $( 'form#add_payment_method' ).length > 0 ) {
				$( document ).ready( this.renderPaymentFields.bind( this ) );
				return;
			}

			// Checkout
			if ( wc_checkout_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', this.renderPaymentFields.bind( this ) );
				return;
			}
		},

		/**
		 * Convenience funnction to get CSS selector for the built-in 'Place Order' button
		 *
		 * @returns {string}
		 */
		getPlaceOrderButtonSelector: function () { return '#place_order'; },

		/**
		 * Convenience funnction to get CSS selector for the custom 'Place Order' button's parent element
		 *
		 * @returns {string}
		 */
		getSubmitButtonTargetSelector: function () { return '#' + this.id + '-card-submit'; },

		/**
		 * Convenience funnction to get CSS selector for the radio input associated with our payment method
		 *
		 * @returns {string}
		 */
		getPaymentMethodRadioSelector: function () { return '.payment_methods input.input-radio[value="' + this.id + '"]'; },

		/**
		 * Convenience function to get CSS selector for stored card radio inputs
		 *
		 * @returns {string}
		 */
		getStoredPaymentMethodsRadioSelector: function () { return '.payment_method_' + this.id + ' .wc-saved-payment-methods input'; },

		/**
		 * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
		 * Global Payments' production servers.
		 *
		 * @returns
		 */
		renderPaymentFields: function () {
			if ( $( '#' + this.id + '-' + this.fieldOptions['card-number-field'].class ).children().length > 0 ) {
				return;
			}

			if ( ! GlobalPayments.configure ) {
				console.log( 'Warning! Payment fields cannot be loaded' );
				return;
			}

			// ensure the submit button's parent is on the page as this is added
			// only after the initial page load
			if ( $( this.getSubmitButtonTargetSelector() ).length === 0 ) {
				this.createSubmitButtonTarget();
			}

			GlobalPayments.configure( this.gatewayOptions );

			this.cardForm = GlobalPayments.ui.form(
				{
					fields: this.getFieldConfiguration(),
					styles: this.getStyleConfiguration()
				}
			);

			this.cardForm.on( 'submit', 'click', this.blockOnSubmit.bind( this ) );
			this.cardForm.on( 'token-success', this.handleResponse.bind( this ) );
			this.cardForm.on( 'token-error', this.handleErrors.bind( this ) );
			this.cardForm.on( 'error', this.handleErrors.bind( this ) );
			GlobalPayments.on( 'error', this.handleErrors.bind( this ) );

			// match the visibility of our payment form
			this.cardForm.ready( function () {
				this.toggleSubmitButtons();
			} );
		},

		/**
		 * Creates the parent for the submit button
		 *
		 * @returns
		 */
		createSubmitButtonTarget: function () {
			var el       = document.createElement( 'div' );
			el.id        = this.getSubmitButtonTargetSelector().replace( '#', '' );
			el.className = 'globalpayments ' + this.id + ' card-submit';
			$( this.getPlaceOrderButtonSelector() ).after( el );
			// match the visibility of our payment form
			this.toggleSubmitButtons();
		},

		/**
		 * Swaps the default WooCommerce 'Place Order' button for our iframe-d button
		 * when one of our gateways is selected.
		 *
		 * @returns
		 */
		toggleSubmitButtons: function () {
			var paymentGatewaySelected = $( this.getPaymentMethodRadioSelector() ).is( ':checked' );
			var savedCardsAvailable    = $( this.getStoredPaymentMethodsRadioSelector() + '[value!="new"]' ).length > 0;
			var newSavedCardSelected   = 'new' === $( this.getStoredPaymentMethodsRadioSelector() + ':checked' ).val();

			var shouldBeVisible = (paymentGatewaySelected && ! savedCardsAvailable) || (savedCardsAvailable && newSavedCardSelected);

			if (shouldBeVisible) {
				// our gateway was selected
				$( this.getSubmitButtonTargetSelector() ).show();
				$( this.getPlaceOrderButtonSelector() ).hide();
			} else {
				// another gateway was selected
				$( this.getSubmitButtonTargetSelector() ).hide();
				$( this.getPlaceOrderButtonSelector() ).show();
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
			if ( ! this.validateTokenResponse( response ) ) {
				return;
			}

			console.log(response);

			var that = this;

			this.cardForm.frames["card-cvv"].getCvv().then(function (c) {
				
				/**
				 * CVV; needed for TransIT gateway processing only
				 *
				 * @type {string}
				 */
				var cvvVal = c;

				var tokenResponseElement =
					/**
					 * Get hidden
					 *
					 * @type {HTMLInputElement}
					 */
					(document.getElementById( that.id + '-token_response' ));
				if ( ! tokenResponseElement) {
					tokenResponseElement      = document.createElement( 'input' );
					tokenResponseElement.id   = that.id + '-token_response';
					tokenResponseElement.name = that.id + '[token_response]';
					tokenResponseElement.type = 'hidden';
					that.getForm().appendChild( tokenResponseElement );
				}

				response.details.cardSecurityCode = cvvVal;
				tokenResponseElement.value = JSON.stringify( response );
				that.placeOrder();
			});
		},

		/**
		 * Places/submits the order to WooCommerce
		 *
		 * Attempts to click the default 'Place Order' button that is used by payment methods.
		 * This is to account for other plugins taking action based on that click event, even
		 * though there are usually better options. If anything fails during that process,
		 * we fall back to calling `this.placeOrder` manually.
		 *
		 * @returns
		 */
		placeOrder: function () {
			try {
				var originalSubmit = $( this.getPlaceOrderButtonSelector() );
				if ( originalSubmit ) {
					originalSubmit.click();
					return;
				}
			} catch ( e ) {
				/* om nom nom */
			}

			$( this.getForm() ).submit();
		},

		/**
		 * Validates the tokenization response
		 *
		 * @param {object} response tokenization response
		 *
		 * @returns {boolean} status of validations
		 */
		validateTokenResponse: function ( response ) {
			this.resetValidationErrors();

			var result = true;

			if (response.details) {
				var expirationDate = new Date( response.details.expiryYear, response.details.expiryMonth - 1 );
				var now            = new Date();
				var thisMonth      = new Date( now.getFullYear(), now.getMonth() );

				if ( ! response.details.expiryYear || ! response.details.expiryMonth || expirationDate < thisMonth ) {
					this.showValidationError( 'card-expiration' );
					result = false;
				}
			}

			if ( response.details && ! response.details.cardSecurityCode ) {
				this.showValidationError( 'card-cvv' );
				result = false;
			}

			return result;
		},

		/**
		 * Hides all validation error messages
		 *
		 * @returns
		 */
		resetValidationErrors: function () {
			$( '.' + this.id + ' .validation-error' ).hide();
		},

		/**
		 * Shows the validation error for a specific payment field
		 *
		 * @param {string} fieldType Field type to show its validation error
		 *
		 * @returns
		 */
		showValidationError: function (fieldType) {
			$( '.' + this.id + '.' + fieldType + ' .validation-error' ).show();
		},

		/**
		 * Handles errors from the payment field iframes
		 *
		 * @param {object} error Details about the error
		 *
		 * @returns
		 */
		handleErrors: function ( error ) {
			this.resetValidationErrors();
			this.unblockOnError();

			if ( ! error.reasons ) {
				return;
			}

			var numberOfReasons = error.reasons.length;

			for ( var i = 0; i < numberOfReasons; i++ ) {
				var reason = error.reasons[i];
				switch ( reason.code ) {
					case 'INVALID_CARD_NUMBER':
						this.showValidationError( 'card-number' );
						break;
					case 'INVALID_CARD_EXPIRATION':
						this.showValidationError( 'card-expiration' );
						break;
					case 'INVALID_CARD_SECURITY_CODE':
						this.showValidationError( 'card-cvv' );
						break;
					case 'MANDATORY_DATA_MISSING':
						var n = reason.message.search( "expiry_month" );
						if ( n>=0 ) {
							this.showValidationError( 'card-expiration' );
							break;
						}
						var n = reason.message.search( "card.cvn.number" );
						if ( n>=0 ) {
							this.showValidationError( 'card-cvv' );
							break;
						}
					case 'INVALID_REQUEST_DATA':
						var n = reason.message.search( "number contains unexpected data" );
						if ( n>=0 ) {
							this.showValidationError( 'card-number' );
							break;
						}
						var n = reason.message.search( "Luhn Check" );
						if ( n>=0 ) {
							this.showValidationError( 'card-number' );
							break;
						}
						var n = reason.message.search( "cvv contains unexpected data" );
						if ( n>=0 ) {
							this.showValidationError( 'card-cvv' );
							break;
						}
					case 'ERROR':
						alert(reason.message);
						break;
					default:
						break;
				}
			}
		},

		/**
		 * Gets payment field config
		 *
		 * @returns {object}
		 */
		getFieldConfiguration: function () {
			return {
				'card-number': {
					placeholder: this.fieldOptions['card-number-field'].placeholder,
					target: '#' + this.id + '-' + this.fieldOptions['card-number-field'].class
				},
				'card-expiration': {
					placeholder: this.fieldOptions['card-expiry-field'].placeholder,
					target: '#' + this.id + '-' + this.fieldOptions['card-expiry-field'].class
				},
				'card-cvv': {
					placeholder: this.fieldOptions['card-cvc-field'].placeholder,
					target: '#' + this.id + '-' + this.fieldOptions['card-cvc-field'].class
				},
				'submit': {
					text: this.getSubmitButtonText(),
					target: this.getSubmitButtonTargetSelector()
				}
			};
		},

		/**
		 * Gets payment field styles
		 *
		 * @returns {object}
		 */
		getStyleConfiguration: function () {
			var imageBase = 'https://api2.heartlandportico.com/securesubmit.v1/token/gp-1.6.0/assets';
			return {
				'html': {
					'font-size': '62.5%'
				},
				'body': {
					'font-size': '1.4rem'
				},
				'#secure-payment-field-wrapper': {
					'postition': 'relative'
				},
				'#secure-payment-field': {
					'-o-transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s',
					'-webkit-box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075)',
					'-webkit-transition': 'border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s',
					'background-color': '#fff',
					'border': '1px solid #cecece',
					'border-radius': '2px',
					'box-shadow': 'none',
					'box-sizing': 'border-box',
					'display': 'block',
					'font-family': '"Roboto", sans-serif',
					'font-size': '11px',
					'font-smoothing': 'antialiased',
					'height': '35px',
					'margin': '5px 0 10px 0',
					'max-width': '100%',
					'outline': '0',
					'padding': '0 10px',
					'transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s',
					'vertical-align': 'baseline',
					'width': '100%'
				},
				'#secure-payment-field:focus': {
					'border': '1px solid lightblue',
					'box-shadow': '0 1px 3px 0 #cecece',
					'outline': 'none'
				},
				'#secure-payment-field[type=button]': {
					'text-align': 'center',
					'text-transform': 'none',
					'white-space': 'nowrap',

					'background-image': 'none',
					'background': '#1979c3',
					'border': '1px solid #1979c3',
					'color': '#ffffff',
					'cursor': 'pointer',
					'display': 'inline-block',
					'font-family': '"Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif',
					'font-weight': '500',
					'padding': '14px 17px',
					'font-size': '1.8rem',
					'line-height': '2.2rem',
					'box-sizing': 'border-box',
					'vertical-align': 'middle',
					'margin': '0',
					'height': 'initial',
					'width': 'initial',
					'flex': 'initial',
					'position': 'absolute',
					'right': '0'
				},
				'#secure-payment-field[type=button]:focus': {
					'outline': 'none',

					'box-shadow': 'none',
					'background': '#006bb4',
					'border': '1px solid #006bb4',
					'color': '#ffffff'
				},
				'#secure-payment-field[type=button]:hover': {
					'background': '#006bb4',
					'border': '1px solid #006bb4',
					'color': '#ffffff'
				},
				'.card-cvv': {
					'background': 'transparent url(' + imageBase + '/cvv.png) no-repeat right',
					'background-size': '60px'
				},
				'.card-cvv.card-type-amex': {
					'background': 'transparent url(' + imageBase + '/cvv-amex.png) no-repeat right',
					'background-size': '60px'
				},
				'.card-number': {
					'background': 'transparent url(' + imageBase + '/logo-unknown@2x.png) no-repeat right',
					'background-size': '52px'
				},
				'.card-number.invalid.card-type-amex': {
					'background': 'transparent url(' + imageBase + '/amex-invalid.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '38px'
				},
				'.card-number.invalid.card-type-discover': {
					'background': 'transparent url(' + imageBase + '/discover-invalid.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '60px'
				},
				'.card-number.invalid.card-type-jcb': {
					'background': 'transparent url(' + imageBase + '/jcb-invalid.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '38px'
				},
				'.card-number.invalid.card-type-mastercard': {
					'background': 'transparent url(' + imageBase + '/mastercard-invalid.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '40px'
				},
				'.card-number.invalid.card-type-visa': {
					'background': 'transparent url(' + imageBase + '/visa-invalid.svg) no-repeat center',
					'background-position-x': '98%',
					'background-size': '50px'
				},
				'.card-number.valid.card-type-amex': {
					'background': 'transparent url(' + imageBase + '/amex.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '38px'
				},
				'.card-number.valid.card-type-discover': {
					'background': 'transparent url(' + imageBase + '/discover.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '60px'
				},
				'.card-number.valid.card-type-jcb': {
					'background': 'transparent url(' + imageBase + '/jcb.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '38px'
				},
				'.card-number.valid.card-type-mastercard': {
					'background': 'transparent url(' + imageBase + '/mastercard.svg) no-repeat center',
					'background-position-x': '98%',
					'background-size': '40px'
				},
				'.card-number.valid.card-type-visa': {
					'background': 'transparent url(' + imageBase + '/visa.svg) no-repeat right center',
					'background-position-x': '98%',
					'background-size': '50px'
				},
				'.card-number::-ms-clear': {
					'display': 'none',
				},
				'input[placeholder]': {
					'letter-spacing': '.5px',
				}
			};
		},

		/**
		 * Gets submit button text
		 *
		 * @returns {string}
		 */
		getSubmitButtonText: function () {
			return $( '#place_order' ).data( 'value' ) || $( '#place_order' ).attr( 'value' );
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
				// Add payment method
				'form#add_payment_method'
			];
			var forms = document.querySelectorAll( checkoutForms.join( ',' ) );
			return forms.item( 0 );
		},

		/**
		 * Blocks checkout UI
		 *
		 * Implementation pulled from `woocommerce/assets/js/frontend/checkout.js`
		 *
		 * @returns
		 */
		blockOnSubmit: function () {
			var $form     = $( this.getForm() );
			var form_data = $form.data();

			if ( 1 !== form_data['blockUI.isBlocked'] ) {
				$form.block(
					{
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					}
				);
			}
		},

		/**
		 * Unblocks checkout UI
		 *
		 * @returns
		 */
		unblockOnError: function () {
			var $form = $( this.getForm() );
			$form.unblock();
		}
	};

	new GlobalPaymentsWooCommerce( globalpayments_secure_payment_fields_params );
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
	(window).wc_checkout_params || {},
	/**
	 * Global `GlobalPayments` reference
	 *
	 * @type {any}
	 */
	(window).GlobalPayments,
	/**
	 * Global `globalpayments_secure_payment_fields_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_secure_payment_fields_params
));
