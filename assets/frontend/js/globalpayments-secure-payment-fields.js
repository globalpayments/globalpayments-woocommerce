// @ts-check

(function (
	$,
	wc_checkout_params,
	GlobalPayments,
	GlobalPayments3DS,
	globalpayments_secure_payment_fields_params,
	globalpayments_secure_payment_threedsecure_params
) {
	/**
	 * Frontend code for Global Payments in WooCommerce
	 *
	 * @param {object} options
	 */
	function GlobalPaymentsWooCommerce( options, threeDSecureOptions ) {

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
		 * Payment field styles
		 */
		this.fieldStyles = options.field_styles;

		/**
		 * Payment gateway options
		 *
		 * @type {object}
		 */
		this.gatewayOptions = options.gateway_options;

		/**
		 * 3DS endpoints
		 */
		this.threedsecure = threeDSecureOptions.threedsecure;

		/**
		 * Order info
		 */
		this.order = threeDSecureOptions.order;

		/**
		 *
		 * @type {null}
		 */
		this.tokenResponse = null;

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

			$( this.getForm() ).on( 'checkout_place_order_globalpayments_gpapi', this.initThreeDSecure.bind( this ) );
			$( document.body ).on( 'checkout_error', function() {
				$('#globalpayments_gpapi-checkout_validated').remove();
			} );

			// Checkout
			if ( 1 == wc_checkout_params.is_checkout ) {
				$( document.body ).on( 'updated_checkout', this.renderPaymentFields.bind( this ) );
				return;
			}

			// Order Pay
			if ( $( document.body ).hasClass( 'woocommerce-order-pay' ) ) {
				$( document ).ready( this.renderPaymentFields.bind( this ) );
				return;
			}

			// Add payment method
			if ( $( 'form#add_payment_method' ).length > 0 ) {
				$( document ).ready( this.renderPaymentFields.bind( this ) );
				return;
			}
		},

		/**
		 * Initiate 3DS process.
		 *
		 * @param e
		 * @returns {boolean}
		 */
		initThreeDSecure: function ( e ) {
			e.preventDefault;
			this.blockOnSubmit();

			var self = this;

			if ( 1 == $('#globalpayments_gpapi-checkout_validated').val() ) {
				return true;
			}

			$.post( this.threedsecure.ajaxCheckoutUrl, $( this.getForm() ).serialize())
				.success( function( result ) {
					if ( -1 !== result.messages.indexOf( self.id + '_checkout_validated' ) ) {
						self.createInputElement( 'checkout_validated', 1 );
						self.threeDSecure();
					} else {
						self.showPaymentError( result.messages );
					}
				})
				.error(	function( jqXHR, textStatus, errorThrown ) {
					self.showPaymentError( errorThrown );
				});

			return false;
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

			var shouldBeVisible = ( paymentGatewaySelected && ( ! savedCardsAvailable  || savedCardsAvailable && newSavedCardSelected ) );
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

			this.tokenResponse = JSON.stringify(response);

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
		 * 3DS Process
		 */
		threeDSecure: function () {
			this.blockOnSubmit();

			var self = this;
			var _form = this.getForm();
			var $form = $( _form );

			GlobalPayments.ThreeDSecure.checkVersion( this.threedsecure.checkEnrollmentUrl, {
				tokenResponse: this.tokenResponse,
				wcTokenId: $( 'input[name="wc-' + this.id + '-payment-token"]:checked', _form ).val(),
				amount: this.order.amount,
				currency: this.order.currency,
				challengeWindow: {
					windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
					displayMode: 'lightbox',
				},
			})
				.then( function( versionCheckData ) {
					if ( versionCheckData.error ) {
						self.showPaymentError( versionCheckData.message );
						return false;
					}
					// Card holder not enrolled in 3D Secure, continue the WooCommerce flow.
					if ( "NOT_ENROLLED" === versionCheckData.enrolled ) {
						$form.submit();
						return true;
					}
					if ( "ONE" === versionCheckData.version ) {
						if ( GlobalPayments.ThreeDSecure.TransactionStatus.ChallengeRequired == versionCheckData.status && "N" == versionCheckData.challenge.response.data.transStatus ) {
							self.showPaymentError( '3DS Authentication failed' );
							return false;
						}
						self.createInputElement( 'serverTransId', versionCheckData.challenge.response.data.MD );
						self.createInputElement( 'PaRes', versionCheckData.challenge.response.data.PaRes );
						$form.submit();
						return false;
					}

					GlobalPayments.ThreeDSecure.initiateAuthentication( self.threedsecure.initiateAuthenticationUrl, {
						tokenResponse: self.tokenResponse,
						wcTokenId: $( 'input[name="wc-' + self.id + '-payment-token"]:checked', _form ).val(),
						versionCheckData: versionCheckData,
						challengeWindow: {
							windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
							displayMode: 'lightbox',
						},
						order: self.order,
					})
						.then( function ( authenticationData ) {
							if ( authenticationData.error ) {
								self.showPaymentError( authenticationData.message );
								return false;
							}
							if ( GlobalPayments.ThreeDSecure.TransactionStatus.ChallengeRequired == authenticationData.status && "N" == authenticationData.challenge.response.data.transStatus ) {
								self.showPaymentError( '3DS Authentication failed' );
								return false;
							}
							self.createInputElement( 'serverTransId', authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID );
							$form.submit();
							return true;
						})
						.catch( function( error ) {
							console.error( error );
							self.showPaymentError( 'Something went wrong while doing 3DS processing.' );
							return false;
						});
				})
				.catch( function( error ) {
					console.error( error );
					self.showPaymentError( 'Something went wrong while doing 3DS processing.' );
					return false;
				});

			$( document ).on( "click", 'img[id^="GlobalPayments-frame-close-"]', this.cancelTransaction.bind( this ) );

			return false;

		},

		/**
		 * Assists with notifying the challenge status, when the user closes the challenge window
		 */
		cancelTransaction: function () {
			window.parent.postMessage({ data: { "transStatus":"N" }, event: "challengeNotification" }, window.location.origin );
		},

		createInputElement: function ( name, value ) {
			var inputElement = (document.getElementById( this.id + '-' + name ));

			if ( ! inputElement) {
				inputElement      = document.createElement( 'input' );
				inputElement.id   = this.id + '-' + name;
				inputElement.name = this.id + '[' + name + ']';
				inputElement.type = 'hidden';
				this.getForm().appendChild( inputElement );
			}

			inputElement.value = value;
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
			$( '.' + this.id + ' .woocommerce-globalpayments-validation-error' ).hide();
		},

		/**
		 * Shows the validation error for a specific payment field
		 *
		 * @param {string} fieldType Field type to show its validation error
		 *
		 * @returns
		 */
		showValidationError: function (fieldType) {
			$( '.' + this.id + '.' + fieldType + ' .woocommerce-globalpayments-validation-error' ).show();

			this.unblockOnError();
		},

		/**
		 * Shows payment error and scrolls to it
		 *
		 * @param {string} message Error message
		 *
		 * @returns
		 */
		showPaymentError: function ( message ) {
			var $form     = $( this.getForm() );

			// Remove notices from all sources
			$( '.woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-globalpayments-checkout-error' ).remove();

			if ( -1 === message.indexOf( 'woocommerce-error' ) ) {
				message = '<ul class="woocommerce-error"><li>' + message + '</li></ul>';
			}
			$form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-globalpayments-checkout-error">' + message + '</div>' );

			$( 'html, body' ).animate( {
				scrollTop: ( $form.offset().top - 100 )
			}, 1000 );

			this.unblockOnError();

			$( document.body ).trigger( 'checkout_error' );
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
						var n = reason.message.search( "card type" );
						if ( n>=0 ) {
							this.showValidationError( 'card-number' );
							break;
						}
						var n = reason.message.search( "expiry_year" );
						if ( n>=0 ) {
							this.showValidationError( 'card-expiration' );
							break;
						}
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
						var n = reason.message.search( "expiry_year" );
						if ( n>=0 ) {
							this.showValidationError( 'card-expiration' );
							break;
						}
					case 'SYSTEM_ERROR_DOWNSTREAM':
						var n = reason.message.search( "card expdate" );
						if ( n>=0 ) {
							this.showValidationError( 'card-expiration' );
							break;
						}
					case 'ERROR':
						this.showPaymentError( reason.message );
						break;
					default:
						this.showPaymentError( reason.message );
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
			return JSON.parse( this.fieldStyles );
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

	new GlobalPaymentsWooCommerce( globalpayments_secure_payment_fields_params, globalpayments_secure_payment_threedsecure_params );
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
	 * Global `GlobalPayments` reference
	 *
	 * @type {any}
	 */
	(window).GlobalPayments.ThreeDSecure,
	/**
	 * Global `globalpayments_secure_payment_fields_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_secure_payment_fields_params,
	/**
	 * Global `globalpayments_secure_payment_threedsecure_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_secure_payment_threedsecure_params || {}
));
