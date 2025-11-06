/* global globalpayments_admin_params */
( function (
	$,
	globalpayments_admin_params,
	globalpayments_admin_txn_params,
	globalpayments_admin_credentials_params
) {
	const { __ } = wp.i18n;

	function GlobalPaymentsAdmin( globalpayments_admin_params, globalpayments_admin_txn_params, globalpayments_admin_credentials_params ) {
		this.id = globalpayments_admin_params.gateway_id;
		this.is_admin_order_page = globalpayments_admin_params.is_admin_order_page || false;
		this.addValueToCredentialsCheckButton();
		this.toggleCredentialsSettings();
		this.toggleValidations();
		this.toggleHppSettings();
		this.attachEventHandlers();
		this.attachCredentialChangeHandlers();
		this.validate_checkbox_fields('.accepted_cards.required');
		this.validate_checkbox_fields('.aca_methods.required');
		this.validate_checkbox_fields('.ob_currencies.required');
	};
	GlobalPaymentsAdmin.prototype = {
		/**
		 * Add important event handlers
		 *
		 * @returns
		 */
		attachEventHandlers: function () {
			if ( ! this.is_admin_order_page ) {
				$( document ).on( 'change', this.getLiveModeSelector(), this.toggleCredentialsSettings.bind( this ) );
				$( document ).on( 'change', this.getEnabledGatewaySelector(), this.toggleValidations.bind( this ) );
				$( document ).on( 'change', $( '.accepted_cards.required' ), this.validate_checkbox_fields.bind( this, '.accepted_cards.required' ) );
				$( document ).on( 'change', $( '.aca_methods.required' ), this.validate_checkbox_fields.bind( this, '.aca_methods.required' ) );
				$( document ).on( 'change', $( '.ob_currencies.required' ), this.validate_checkbox_fields.bind( this, '.ob_currencies.required' ) );
				$( document ).on( 'change', this.getPaymentInterfaceSelector(), this.toggleHppSettings.bind( this ) );
				$( document ).on( 'click', this.getCheckCredentialsButtonSelector(), this.checkApiCredentials.bind( this , 'account_name_dropdown', 'account_name' ) );
				$( document ).on( 'load ', this.checkApiCredentials( 'account_name_dropdown', 'account_name', 'change' ));
			}
			// Admin Pay for Order
			$( '#customer_user' ).on( 'change', this.updatePaymentMethods );
			$( '.wc-globalpayments-pay-order' ).on( 'click', this.payForOrder );
			// Admin View Transaction Details
			$( '.wc-globalpayments-transaction-info' ).on( 'click', this.viewTransactionStatus );
			$( document.body ).on( 'wc_backbone_modal_loaded', this.modalLoaded.bind( this ) );

			$( '#woocommerce_globalpayments_clicktopay_payment_action' ).prop( 'disabled', true );
			$( '#woocommerce_globalpayments_fasterpayments_payment_action' ).prop( 'disabled', true );
			$( '#woocommerce_globalpayments_bankpayment_payment_action' ).prop( 'disabled', true );

			var self = this;
			$( document ).on( 'ready',function () {
				var selector = '';
				if( $( '#woocommerce_' + self.id + '_is_production' ).is( ':checked' ) ) {
					selector = 'woocommerce_globalpayments_gpapi_';
				} else {
					selector = 'woocommerce_globalpayments_gpapi_sandbox_';
				}
				$('#' + selector + 'account_name_dropdown').on('change', function () {
					// Get the selected value from the dropdown
					const selectedValue =  $(this).find('option:selected').text();

					// Set the value of the textbox
					$('#' + selector + 'account_name').val(selectedValue);
				});
			});
		},

		checkApiCredentials: function ( setting1, setting2, event = '' ) {
			$(' .notice ').remove();

			var gateway_app_id = this.getGatewaySetting( 'app_id' );
			var gateway_app_key = this.getGatewaySetting( 'app_key' );
			var environment = 0;
			var selector = '';

			if ( ! gateway_app_id || ! gateway_app_key ) {
				alert( __( 'Please be sure that you have filled AppId and AppKey fields!', 'globalpayments-gateway-provider-for-woocommerce' ) );
				return;
			}
			if ( this.isLiveMode() ) {
				environment = 1;
				$('#woocommerce_globalpayments_gpapi_' + setting2).hide();
				selector = 'woocommerce_globalpayments_gpapi_';
			}
			 else {
				$('#woocommerce_globalpayments_gpapi_sandbox_' + setting2).hide();
				selector = 'woocommerce_globalpayments_gpapi_sandbox_';
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
					var dropdown = '';
					if ( environment ) {
						dropdown = $('#woocommerce_globalpayments_gpapi_' + setting1);
					} else {
						dropdown = $('#woocommerce_globalpayments_gpapi_sandbox_' + setting1);
					}
					dropdown.empty();
					if(response.accounts) {
						var default_value;
						response.accounts.forEach(item => {
							if ( environment ) {
								if( $('#woocommerce_globalpayments_gpapi_' + setting2).val() == item.name ) {
									default_value = item.id;
								}
							} else {
								if( $('#woocommerce_globalpayments_gpapi_sandbox_' + setting2).val() == item.name ) {
									default_value = item.id;
								}
							}

							dropdown.append(
								$('<option>', {
									value: item.id,
									text: item.name,
								})
							);
						});
						$('#'+selector + setting1).val( default_value );
					}

					if( !event || event.type) {
						self.displayNotice( 'success', response.message );
					}
				}
			}).fail( function ( xhr, textStatus, errorThrown ) {
				window.alert( errorThrown );
			})
		},

		addValueToCredentialsCheckButton: function() {
			$( '#woocommerce_globalpayments_gpapi_credentials_api_check' ).attr( 'value', __( 'Credentials check', 'globalpayments-gateway-provider-for-woocommerce' ) );
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
		 * Checks if checkbox has at least one selected
		 * @param fieldClass
		 */
		validate_checkbox_fields: function( fieldClass ) {
			if ( this.isEnabled() ) {
				var checksitems = $( fieldClass );
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
			this.validate_checkbox_fields( '.accepted_cards.required' );
			this.validate_checkbox_fields( '.aca_methods.required' );
			this.validate_checkbox_fields( '.ob_currencies.required' );

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
			if ( display ) {
				$( '#woocommerce_globalpayments_gpapi_account_name' ).hide();
			}
			else {
				$( '#woocommerce_globalpayments_gpapi_sandbox_account_name' ).hide();
			}

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
		 * Toggle Hosted Payment Page settings
		 * @returns {void}
		 */
		toggleHppSettings: function () {
			//Note: this should also target the AVS / CVN as HPP does not return these values, update will be needed in future
			let display = this.isHppSelected();
			let hppSelector = `#woocommerce_${this.id}_section_hpp`;
			let hppSectionTable = document.querySelector( hppSelector + '~ table.form-table' );
			let hppSectionTitle = document.querySelector( hppSelector );

			if ( display && hppSectionTable && hppSectionTitle ) {
				hppSectionTable.style.display = 'block';
				hppSectionTitle.style.display = 'block';
			} else if ( hppSectionTable && hppSectionTitle ) {
				hppSectionTable.style.display = 'none';
				hppSectionTitle.style.display = 'none';
			} else {
				return;
			}

			//toggle DiUI Polish APM fields visibility
			if (document.getElementById('woocommerce_globalpayments_gpapi_enable_blik') !== null) {
				let blikRow = document.getElementById('woocommerce_globalpayments_gpapi_enable_blik').parentElement.parentElement.parentElement.parentElement;
				let obRow = document.getElementById('woocommerce_globalpayments_gpapi_enable_bank_select').parentElement.parentElement.parentElement.parentElement;
				if (display) {
					blikRow.style.display = 'none';
					obRow.style.display = 'none';
				} else {
					blikRow.style.display = '';
					obRow.style.display = '';
				}
			}
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

		/**
		 * Convenience function to get CSS selector for the Payment Interface selector
		 *
		 * @returns {string}
		 */
		getPaymentInterfaceSelector: function () {
			return 'select#woocommerce_' + this.id + '_payment_interface';
		},

		/*
		 * Convenience function to check if Hosted Payment Page is selected
		 *
		 * @returns {boolean}
		*/
		isHppSelected: function () {
			return "hpp" === document.querySelector( this.getPaymentInterfaceSelector() )?.value;
		},

		/**
		 * Attach event handlers for App Id and App Key changes to clear Account Name
		 */
		attachCredentialChangeHandlers: function () {
			var self = this;
            var originalValues = {};

			// Live mode credential change handlers
			$(document).on('focus', '#woocommerce_' + this.id + '_app_id', function() {
				originalValues.appId = $(this).val();
			});

            $(document).on('blur', '#woocommerce_' + this.id + '_app_id', function() {
                var currentValue = $(this).val();
                if (originalValues.appId !== currentValue) {
                    self.clearAccountName(true);
                }
            });

            $(document).on('focus', '#woocommerce_' + this.id + '_app_key', function() {
                originalValues.appKey = $(this).val();
            });

            $(document).on('blur', '#woocommerce_' + this.id + '_app_key', function() {
                var currentValue = $(this).val();
                if (originalValues.appKey !== currentValue) {
                    self.clearAccountName(true);
                }
            });

            // Sandbox mode credential change handlers
            $(document).on('focus', '#woocommerce_' + this.id + '_sandbox_app_id', function() {
                originalValues.sandboxAppId = $(this).val();
            });

            $(document).on('blur', '#woocommerce_' + this.id + '_sandbox_app_id', function() {
                var currentValue = $(this).val();
                if (originalValues.sandboxAppId !== currentValue) {
                    self.clearAccountName(false);
                }
            });

            $(document).on('focus', '#woocommerce_' + this.id + '_sandbox_app_key', function() {
                originalValues.sandboxAppKey = $(this).val();
            });

            $(document).on('blur', '#woocommerce_' + this.id + '_sandbox_app_key', function() {
                var currentValue = $(this).val();
                if (originalValues.sandboxAppKey !== currentValue) {
                    self.clearAccountName(false);
                }
            });
		},

		/**
		 * Clear account name field and dropdown for the specified mode
		 */
		clearAccountName: function (isLiveMode) {
			if (isLiveMode) {
				// Clear live mode account name
				$('#woocommerce_' + this.id + '_account_name').val('').trigger('change');
				var liveDropdown = $('#woocommerce_' + this.id + '_account_name_dropdown');
				if (liveDropdown.length > 0) {
					// Clear all options
					liveDropdown.empty();
				}
			} else {
				// Clear sandbox mode account name
				$('#woocommerce_' + this.id + '_sandbox_account_name').val('').trigger('change');
				var sandboxDropdown = $('#woocommerce_' + this.id + '_sandbox_account_name_dropdown');
				if (sandboxDropdown.length > 0) {
					// Clear all options
					sandboxDropdown.empty();
				}
			}
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
