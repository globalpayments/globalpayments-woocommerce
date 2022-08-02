// @ts-check

(function (
	$,
	wc_checkout_params,
	GlobalPayments,
	GlobalPayments3DS,
	globalpayments_secure_payment_fields_params,
	globalpayments_secure_payment_threedsecure_params,
	helper
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
		this.order = {};

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
			var self = this;

			// General
			$( '#order_review, #add_payment_method' ).on( 'click', '.payment_methods input.input-radio', helper.toggleSubmitButtons.bind( helper ) );

			// Saved payment methods
			$( document.body ).on(
				'updated_checkout wc-credit-card-form-init',
				function () {
					$( '.payment_method_' + self.id + ' .wc-saved-payment-methods' ).on( 'change', ':input.woocommerce-SavedPaymentMethods-tokenInput', helper.toggleSubmitButtons.bind( helper ) );
				}
			);

			$( helper.getForm() ).on( 'checkout_place_order_globalpayments_gpapi', this.initThreeDSecure.bind( this ) );
			$( document.body ).on( 'checkout_error', function() {
				$('#globalpayments_gpapi-checkout_validated').remove();
				$('#globalpayments_gpapi-serverTransId').remove();
				$('#globalpayments_gpapi-PaRes').remove();
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

			// Admin Pay for Order
			$( document.body ).on( 'globalpayments_pay_order_modal_loaded', this.renderPaymentFields.bind( this ) );
			$( document.body ).on( 'globalpayments_pay_order_modal_error', function( event, message ) {
				self.showPaymentError( message );
			} );
		},

		/**
		 * Initiate 3DS process.
		 *
		 * @param e
		 * @returns {boolean}
		 */
		initThreeDSecure: function ( e ) {
			e.preventDefault;
			helper.blockOnSubmit();

			var self = this;

			if ( 1 == $('#globalpayments_gpapi-checkout_validated').val() ) {
				return true;
			}

			$.post( this.threedsecure.ajaxCheckoutUrl, $( helper.getForm() ).serialize())
				.done( function( result ) {
					if ( -1 !== result.messages.indexOf( self.id + '_checkout_validated' ) ) {
						helper.createInputElement( self.id, 'checkout_validated', 1 );
						self.order = helper.order;
						self.threeDSecure();
					} else {
						self.showPaymentError( result.messages );
					}
				})
				.fail(	function( jqXHR, textStatus, errorThrown ) {
					self.showPaymentError( errorThrown );
				});

			return false;
		},

		/**
		 * Checks if an order has input for the shipping address
		 *
		 * @returns {boolean|*|jQuery}
		 */
		isDifferentShippingAddress: function () { return $( '#ship-to-different-address-checkbox' ).length > 0 && $( '#ship-to-different-address-checkbox' ).is( ':checked' ); },

		/**
		 * Get checkout billing address
		 *
		 * @returns {{country: (*|jQuery), city: (*|jQuery), postalCode: (*|jQuery), streetAddress1: (*|jQuery), streetAddress2: (*|jQuery), state: (*|jQuery)}}
		 */
		getBillingAddressFormData: function () {
			return {
				streetAddress1: $( '#billing_address_1' ).val(),
				streetAddress2: $( '#billing_address_2' ).val(),
				city: $( '#billing_city' ).val(),
				state: $( '#billing_state' ).val(),
				postalCode: $( '#billing_postcode' ).val(),
				country: $( '#billing_country' ).val(),
			};
		},

		/**
		 * Get checkout shipping address
		 *
		 * @returns {{country: (*|jQuery), city: (*|jQuery), postalCode: (*|jQuery), streetAddress1: (*|jQuery), streetAddress2: (*|jQuery), state: (*|jQuery)}}
		 */
		getShippingAddressFormData: function () {
			return {
				streetAddress1: $( '#shipping_address_1' ).val(),
				streetAddress2: $( '#shipping_address_2' ).val(),
				city: $( '#shipping_city' ).val(),
				state: $( '#shipping_state' ).val(),
				postalCode: $( '#shipping_postcode' ).val(),
				country: $( '#shipping_country' ).val(),
			};
		},

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
			var gatewayConfig = this.gatewayOptions;
			if ( gatewayConfig.error ) {
				this.showPaymentError( gatewayConfig.message );
			}

			// ensure the submit button's parent is on the page as this is added
			// only after the initial page load
			if ( $( helper.getSubmitButtonTargetSelector( this.id ) ).length === 0 ) {
				helper.createSubmitButtonTarget( this.id );
			}

			GlobalPayments.configure( gatewayConfig );
			this.cardForm = GlobalPayments.ui.form(
				{
					fields: this.getFieldConfiguration(),
					styles: this.getStyleConfiguration()
				}
			);
			this.cardForm.on( 'submit', 'click', helper.blockOnSubmit.bind( this ) );
			this.cardForm.on( 'token-success', this.handleResponse.bind( this ) );
			this.cardForm.on( 'token-error', this.handleErrors.bind( this ) );
			this.cardForm.on( 'error', this.handleErrors.bind( this ) );
			GlobalPayments.on( 'error', this.handleErrors.bind( this ) );

			var self = this;
			// match the visibility of our payment form
			this.cardForm.ready( function () {
				helper.toggleSubmitButtons();
			} );

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

			var self = this;

			this.cardForm.frames["card-cvv"].getCvv().then( function ( c ) {
				
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
					(document.getElementById( self.id + '-token_response' ));
				if ( ! tokenResponseElement) {
					tokenResponseElement      = document.createElement( 'input' );
					tokenResponseElement.id   = self.id + '-token_response';
					tokenResponseElement.name = self.id + '[token_response]';
					tokenResponseElement.type = 'hidden';
					helper.getForm().appendChild( tokenResponseElement );
				}

				response.details.cardSecurityCode = cvvVal;
				tokenResponseElement.value = JSON.stringify( response );
				helper.placeOrder();
			});
		},

		/**
		 * 3DS Process
		 */
		threeDSecure: function () {
			helper.blockOnSubmit();

			var self = this;
			var _form = helper.getForm();
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
					if ( "NOT_ENROLLED" === versionCheckData.status && "YES" !== versionCheckData.liabilityShift ) {
						self.showPaymentError( '3DS Authentication failed. Please try again.' );
						return false;
					}
					if ( "NOT_ENROLLED" === versionCheckData.status && "YES" === versionCheckData.liabilityShift ) {
						$form.submit();
						return true;
					}
					if ( "ONE" === versionCheckData.version ) {
						helper.createInputElement( self.id, 'serverTransId', versionCheckData.challenge.response.data.MD || versionCheckData.serverTransactionId );
						helper.createInputElement( self.id, 'PaRes', versionCheckData.challenge.response.data.PaRes || '');
						$form.submit();
						return false;
					}

					var addressMatch = ! self.isDifferentShippingAddress();
					var billingAddress = self.getBillingAddressFormData();
					var shippingAddress = addressMatch ? billingAddress : self.getShippingAddressFormData();

					GlobalPayments.ThreeDSecure.initiateAuthentication( self.threedsecure.initiateAuthenticationUrl, {
						tokenResponse: self.tokenResponse,
						wcTokenId: $( 'input[name="wc-' + self.id + '-payment-token"]:checked', _form ).val(),
						versionCheckData: versionCheckData,
						challengeWindow: {
							windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
							displayMode: 'lightbox',
						},
						order: {
							amount: self.order.amount,
							currency: self.order.currency,
							billingAddress: billingAddress,
							shippingAddress: shippingAddress,
							addressMatchIndicator: addressMatch,
							customerEmail: $( '#billing_email' ).val(),
						}
					})
						.then( function ( authenticationData ) {
							if ( authenticationData.error ) {
								self.showPaymentError( authenticationData.message );
								return false;
							}
							helper.createInputElement( self.id, 'serverTransId', authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID || versionCheckData.serverTransactionId );
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
				var now = new Date();
				var thisMonth = new Date( now.getFullYear(), now.getMonth() );

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

			helper.unblockOnError();
		},

		/**
		 * Shows payment error and scrolls to it
		 *
		 * @param {string} message Error message
		 *
		 * @returns
		 */
		showPaymentError: function ( message ) {
			var $form = $( helper.getForm() );

			// Remove notices from all sources
			$( '.woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-globalpayments-checkout-error' ).remove();

			if ( -1 === message.indexOf( 'woocommerce-error' ) ) {
				message = '<ul class="woocommerce-error"><li>' + message + '</li></ul>';
			}
			$form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-globalpayments-checkout-error">' + message + '</div>' );

			$( 'html, body' ).animate( {
				scrollTop: ( $form.offset().top - 100 )
			}, 1000 );

			helper.unblockOnError();

			if ( 1 == wc_checkout_params.is_checkout ) {
				$( document.body ).trigger( 'checkout_error' );
			}
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
			console.error(error);
			if ( ! error.reasons ) {
				this.showPaymentError( 'Something went wrong. Please contact us to get assistance.' );
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
						var n = reason.message.search("card.number");
						if (n >= 0) {
							this.showValidationError('card-number');
							break;
						}
					case 'SYSTEM_ERROR_DOWNSTREAM':
						var n = reason.message.search( "card expdate" );
						if ( n>=0 ) {
							this.showValidationError( 'card-expiration' );
							break;
						}
					case 'ERROR':
						if(reason.message == "IframeField: target cannot be found with given selector")
							break;
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
					target: helper.getSubmitButtonTargetSelector( this.id )
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
			var selector = helper.getPlaceOrderButtonSelector();
			return $( selector ).data( 'value' ) || $( selector ).attr( 'value' );
		},
	};

	new GlobalPaymentsWooCommerce( globalpayments_secure_payment_fields_params, globalpayments_secure_payment_threedsecure_params );
}(
	/**
	 * Global `jQuery` reference
	 *
	 * @type {any}
	 */
	( window ).jQuery,
	/**
	 * Global `wc_checkout_params` reference
	 *
	 * @type {any}
	 */
	( window ).wc_checkout_params || {},
	/**
	 * Global `GlobalPayments` reference
	 *
	 * @type {any}
	 */
	( window ).GlobalPayments,
	/**
	 * Global `GlobalPayments` reference
	 *
	 * @type {any}
	 */
	( window ).GlobalPayments.ThreeDSecure,
	/**
	 * Global `globalpayments_secure_payment_fields_params` reference
	 *
	 * @type {any}
	 */
	( window ).globalpayments_secure_payment_fields_params || {},
	/**
	 * Global `globalpayments_secure_payment_threedsecure_params` reference
	 *
	 * @type {any}
	 */
	( window ).globalpayments_secure_payment_threedsecure_params || {},
	/**
	 * Global `helper` reference
	 *
	 * @type {any}
	 */
	( window ).GlobalPaymentsHelper
));
