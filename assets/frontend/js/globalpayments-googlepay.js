( function (
	$,
	globalpayments_googlepay_params,
	helper
) {
	function GooglePayWoocommerce ( options ) {
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

		/**
		 *
		 * @type {null}
		 */
		this.paymentsClient = null;

		this.attachEventHandlers();
	};

	GooglePayWoocommerce.prototype = {
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

			// Order Pay
			if ( $( document.body ).hasClass( 'woocommerce-order-pay' ) ) {
				$( document ).ready( this.initialize.bind( this ) );
				return;
			}
		},

		initialize: function () {
			var self = this
			self.setGooglePaymentsClient();

			self.paymentsClient.isReadyToPay(
				self.getGoogleIsReadyToPayRequest()
			).then( function ( response ) {
				if ( response.result ) {
					self.addGooglePayButton( self.id );
				} else {
					helper.hidePaymentMethod( self.id );
				}
			} ).catch( function ( err ) {
				console.error( err );
			} );
		},

		getBaseRequest: function () {
			return {
				apiVersion: 2,
				apiVersionMinor: 0
			};
		},

		/**
		 * Google Merchant Id
		 */
		getGoogleMerchantId: function () {
			return this.paymentMethodOptions.google_merchant_id;
		},

		/**
		 * Google Merchant Display Name
		 */
		getGoogleMerchantName: function () {
			return this.paymentMethodOptions.google_merchant_name;
		},

		/**
		 * Environment
		 */
		getEnvironment: function () {
			return this.paymentMethodOptions.env;
		},

		/**
		 * BTN Color
		 */
		getBtnColor: function () {
			return this.paymentMethodOptions.button_color;
		},

		getAllowedCardNetworks: function () {
			return this.paymentMethodOptions.cc_types;
		},

		getAllowedCardAuthMethods: function () {
			return this.paymentMethodOptions.aca_methods;
		},

		getTokenizationSpecification: function () {
			return {
				type: 'PAYMENT_GATEWAY',
				parameters: {
					'gateway': 'globalpayments',
					'gatewayMerchantId': this.paymentMethodOptions.global_payments_merchant_id
				}
			}
		},

		getBaseCardPaymentMethod: function () {
			return {
				type: 'CARD',
				parameters: {
					allowedAuthMethods: this.getAllowedCardAuthMethods(),
					allowedCardNetworks: this.getAllowedCardNetworks(),
					billingAddressRequired: true
				}
			}
		},

		getCardPaymentMethod: function () {
			return Object.assign(
				{},
				this.getBaseCardPaymentMethod(),
				{
					tokenizationSpecification: this.getTokenizationSpecification()
				}
			);
		},

		getGoogleIsReadyToPayRequest: function () {
			return Object.assign(
				{},
				this.getBaseRequest(),
				{
					allowedPaymentMethods: [ this.getBaseCardPaymentMethod() ]
				}
			);
		},

		getGooglePaymentDataRequest: function () {
			var paymentDataRequest = Object.assign({}, this.getBaseRequest() );
			paymentDataRequest.allowedPaymentMethods = [ this.getCardPaymentMethod() ];
			paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();
			paymentDataRequest.merchantInfo = {
				merchantId: this.getGoogleMerchantId(),
				merchantName: this.getGoogleMerchantName()
			}

			return paymentDataRequest;
		},

		getGoogleTransactionInfo: function () {
			return {
				totalPriceStatus: 'FINAL',
				totalPrice: this.order.amount.toString(),
				currencyCode: this.order.currency
			};
		},

		/**
		 * Init google pay client
		 */
		setGooglePaymentsClient: function () {
			var self = this;
			if ( null === this.paymentsClient ) {
				this.paymentsClient = new google.payments.api.PaymentsClient( {
					environment: self.getEnvironment()
				} );
			}
		},

		/**
		 * Add the google pay button to the DOM
		 */
		addGooglePayButton: function () {
			helper.createSubmitButtonTarget( this.id );

			var self = this
			var button = this.paymentsClient.createButton( {
					buttonColor: self.getBtnColor(),
					onClick: function () {
						self.onGooglePaymentButtonClicked();
					}
				} );

			$( helper.getSubmitButtonTargetSelector( this.id ) ).append( button );
		},

		onGooglePaymentButtonClicked: function () {
			var self = this;
			this.order = helper.order;
			var paymentDataRequest = this.getGooglePaymentDataRequest();
			paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();

			this.paymentsClient.loadPaymentData( paymentDataRequest )
				.then( function ( paymentData ) {
					helper.createInputElement(
						self.id,
						'dw_token',
						JSON.stringify( JSON.parse( paymentData.paymentMethodData.tokenizationData.token ) )
					);

					helper.createInputElement(
						self.id,
						'cardHolderName',
						paymentData.paymentMethodData.info.billingAddress.name
					);

					return helper.placeOrder();
				} )
				.catch( function ( err ) {
					console.error( err );
				} );
		},
	}

	new GooglePayWoocommerce( globalpayments_googlepay_params );
}(
	( window ).jQuery,
	( window ).globalpayments_googlepay_params || {},
	( window ).GlobalPaymentsHelper
) );
