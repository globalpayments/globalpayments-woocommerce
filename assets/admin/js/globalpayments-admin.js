/* global globalpayments_admin_params */

( function (
	$,
	globalpayments_admin_params,
	globalpayments_admin_txn_params,
	globalpayments_admin_credentials_params
) {
	function GlobalPaymentsAdmin( globalpayments_admin_params, globalpayments_admin_txn_params, globalpayments_admin_credentials_params ) {
		this.id = globalpayments_admin_params.gateway_id;
		this.addValueToCredentialsCheckButton();
		this.toggleCredentialsSettings();
		this.toggleValidations();
		this.attachEventHandlers();
		this.validate_cc_types();
	};
	GlobalPaymentsAdmin.prototype = {
		/**
		 * Add important event handlers
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			$( document ).on( 'change', this.getLiveModeSelector(), this.toggleCredentialsSettings.bind( this ) );
			$( document ).on( 'change', this.getEnabledGatewaySelector(), this.toggleValidations.bind( this ) );
			$( document ).on( 'change', $( '.accepted_cards.required' ), this.validate_cc_types.bind( this ) );
			$( document ).on( 'click', this.getCheckCredentialsButtonSelector(), this.checkApiCredentials.bind( this ));

			// Admin Pay for Order
			$( '#customer_user' ).on( 'change', this.updatePaymentMethods );
			$( '.wc-globalpayments-pay-order' ).on( 'click', this.payForOrder );
			// Admin View Transaction Details
			$( '.wc-globalpayments-transaction-info' ).on( 'click', this.viewTransactionStatus );
			$( document.body ).on( 'wc_backbone_modal_loaded', this.modalLoaded.bind( this ) );

			$( '#woocommerce_globalpayments_clicktopay_payment_action' ).prop( 'disabled', true );
		},

		checkApiCredentials: function ( e ) {
			e.preventDefault();
			$(' .notice ').remove();

			var gateway_app_id = this.getGatewaySetting( 'app_id' );
			var gateway_app_key = this.getGatewaySetting( 'app_key' );
			var environment = 0;

			if ( ! gateway_app_id || ! gateway_app_key ) {
				alert( 'Please be sure that you have filled AppId and AppKey fields!' );
				return;
			}
			if ( this.isLiveMode() ) {
				environment = 1;
			}
			var self = this;

			$.ajax({
				url: globalpayments_admin_credentials_params.check_api_credentials_url,
				method: 'POST',
				data: {
					_wpnonce: globalpayments_admin_credentials_params._wpnonce,
					app_id: gateway_app_id,
					app_key: gateway_app_key,
					environment: environment,
				}
			}).done( function ( response ) {
				if ( response.error ) {
					self.displayNotice( 'error', response.message );
				} else {
					self.displayNotice( 'success', response.message );
				}
			}).fail( function ( xhr, textStatus, errorThrown ) {
				window.alert( errorThrown );
			})
		},

		addValueToCredentialsCheckButton: function() {
			$( '#woocommerce_globalpayments_gpapi_credentials_api_check' ).attr( 'value', 'Credentials check' );
		},

		getGatewaySetting: function ( setting ) {
			if ( this.isLiveMode() ) {
				return $( '#woocommerce_globalpayments_gpapi_' + setting ).val().trim();
			} else {
				return $( '#woocommerce_globalpayments_gpapi_sandbox_' + setting ).val().trim();
			}
		},

		displayNotice: function ( type, message ) {
			var notice = $( '<div class="notice notice-' + type + ' inline' + '"><p>' + message + '</p></div>' );
			$( 'html, body' ).animate( { scrollTop: $( '#wpwrap').offset().top }, 'slow', function() {
				notice.insertAfter( $( '#mainform' ).find( 'h1' ) );
			});
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
		 * Enable modal template.
		 *
		 * @param e
		 */
		viewTransactionStatus: function ( e ) {
			e.preventDefault();
			const transactionInfo = GlobalPaymentsAdmin.transactionInfo = $(this);
			if ( transactionInfo.data( 'transaction-info' ) ) {
				$( this ).WCBackboneModal({
					template: 'wc-globalpayments-transaction-info-modal',
					variable: {
						transaction_info: $( this ).data( 'transaction-info' )
					}
				});
			} else {
				$( '.wc-globalpayments-transaction-info' ).prop( 'disabled', true );

				$.ajax({
					url: globalpayments_admin_txn_params.transaction_info_url,
					method: 'POST',
					data: {
						_wpnonce: globalpayments_admin_txn_params._wpnonce,
						transactionId: globalpayments_admin_txn_params.transaction_id
					}
				}).done( function ( response ) {
					if ( response.error ) {
						$( this ).WCBackboneModal({
							template: 'wc-globalpayments-transaction-info-modal',
							variable: {
								error_message: response.message
							}
						});
					} else {
						transactionInfo.data( 'transaction-info', response );

						$( this ).WCBackboneModal({
							template: 'wc-globalpayments-transaction-info-modal',
							variable: {
								transaction_info: response
							}
						});
					}
				}).fail( function (xhr, textStatus, errorThrown) {
					window.alert(errorThrown);
				}).always( function () {
					$( '.wc-globalpayments-transaction-info' ).prop( 'disabled', false );
				})
			}
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
				case 'wc-globalpayments-transaction-info-modal':
					$( document.body ).trigger( 'globalpayments_transaction_info_modal_loaded' );
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
		 * Checks if gateway setting is enabled
		 *
		 * @returns {*|jQuery}
		 */
		isEnabled: function() {
			return $( this.getEnabledGatewaySelector() ).is( ':checked' );
		},

		/**
		 * Checks if cc_types at least one selected
		 */
		validate_cc_types: function () {
			if ( this.isEnabled() ) {
				var checksitems = $( '.accepted_cards.required' );
				var required = true;
				if ( checksitems && checksitems.length > 0 ) {
					checksitems.each( function() {
						if ( $( this ).is( ':checked' ) ) {
							required = false;
							checksitems.prop( 'required', false );
							return;
						}
					} );
					if ( required ) {
						checksitems.prop( 'required', true );
					}
				}
			}
		},

		/**
		 * Toggle validations when enabled gateway settings
		 */
		toggleValidations: function () {
			this.validate_cc_types();

			var button = $('.woocommerce-save-button');
			if ( this.isEnabled() ) {
				button.removeAttr( "formnovalidate" );
			} else {
				button.attr( "formnovalidate","");
			}
		},

		/**
		 * Toggle required settings
		 */
		toggleRequiredSettings: function () {
			var list =  $( '.required' );
			list.each( function() {
				if ( $( this ).is( ':visible' ) ) {
					$( this ).prop( 'required', true );
				} else {
					$( this ).prop( 'required', false );
				}
			});
		},

		/**
		 * Toggle gateway credentials settings
		 */
		toggleCredentialsSettings: function () {
			var display = this.isLiveMode();

			$( '.live-toggle' ).parents( 'tr' ).toggle( display );
			$( '.sandbox-toggle' ).parents( 'tr' ).toggle( !display );

			this.toggleRequiredSettings();
		},

		/**
		 * Convenience function to get CSS selector for the "Live Mode" setting
		 *
		 * @returns {string}
		 */
		getLiveModeSelector: function () {
			return '#woocommerce_' + this.id + '_is_production';
		},

		/**
		 * Convenience function to get CSS selector for the "Enabled" setting
		 *
		 * @returns {string}
		 */
		getEnabledGatewaySelector: function () {
			return '#woocommerce_' + this.id + '_enabled';
		},

		/**
		 * Convenience function to get Check Credentials button selector
		 *
		 * @returns {string}
		 */
		getCheckCredentialsButtonSelector: function () {
			return '#woocommerce_globalpayments_gpapi_credentials_api_check';
		},
	};
	new GlobalPaymentsAdmin( globalpayments_admin_params, globalpayments_admin_txn_params, globalpayments_admin_credentials_params );
}(
	/**
	 * Global `jQuery` reference
	 *
	 * @type {any}
	 */
	(window).jQuery,
	/**
	 * Global `globalpayments_admin_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_admin_params || {},
	/**
	 * Global `globalpayments_admin_txn_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_admin_txn_params || {},
	/**
	 * Global `globalpayments_admin_credentials_params` reference
	 *
	 * @type {any}
	 */
	(window).globalpayments_admin_credentials_params || {}
));
