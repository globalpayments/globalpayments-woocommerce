import { getPaymentMethods } from '@woocommerce/blocks-registry';

const getElement = ( selector ) => {
	return document.querySelector( selector );
};

const getAllElements = ( selector ) => {
	return document.querySelectorAll( selector );
};

const addClass = ( element, className ) => {
	element?.classList.add( className );

	return element;
};

const removeClass = ( element, className ) => {
	element?.classList.remove( className );

	return element;
};

const hideElement = ( element ) => {
	if (element?.style) {
		element.style.display = 'none';
	}

	return element;
};

const showElement = ( element ) => {
	if (element?.style) {
		element.style.display = '';
	}

	return element;
};

let params = {};

export const helper = ( helper_params ) => {
	params = helper_params;

	return {
		...helper_functions,
		order: params.order
	};
};

const helper_functions = {
	/**
	 * Get order info
	 */
	getOrderInfo: () => {
		helper_functions.blockOnSubmit();

		fetch( params.orderInfoUrl )
			.then( ( res ) => res.json() )
			.then( ( result ) => {
				params.order = result.message;
			} )
			.catch( ( jqXHR, textStatus, errorThrown ) => {
				console.log( errorThrown );
			} )
			.finally( () => {
				helper_functions.unblockOnError();
			} );
	},
	/**
	 * Convenience function to get CSS selector for the built-in 'Place Order' button
	 *
	 * @returns {string}
	 */
	getPlaceOrderButtonSelector: () => {
		return '.wc-block-components-checkout-place-order-button';
	},

	/**
	 * Convenience function to get CSS selector for the custom 'Place Order' button's parent element
	 *
	 * @param {string} id
	 * @returns {string}
	 */
	getSubmitButtonTargetSelector: ( id ) => {
		return '#' + id + '-card-submit';
	},

	/**
	 * Convenience function to get CSS selector for the radio input associated with our payment method
	 *
	 * @returns {string}
	 */
	getPaymentMethodRadioSelector: ( id ) => {
		return '#payment-method input[id*="radio-control-wc-payment-method-options-' + id + '"]';
	},

	/**
	 * Convenience function to get CSS selector for stored card radio inputs
	 *
	 * @returns {string}
	 */
	getStoredPaymentMethodsRadioSelector: () => {
		return '#payment-method input[id*="radio-control-wc-payment-method-saved-tokens-"]:checked';
	},

	isOnlyGatewayMethodDisplayed: ( id ) => {
		const list = [ ...getAllElements( '#payment-method input[id*="radio-control-wc-payment-method-"]' ) ];
		const gateway = getElement( helper_functions.getPaymentMethodRadioSelector( id ) );
		return list.includes( gateway ) && list.length === 1;
	},
	isFirstPaymentMethod: ( id ) => {
		return document.querySelector('#payment-method input').value === id;
	},

	/**
	 * Swaps the default WooCommerce 'Place Order' button for our iframe-d button
	 * or digital wallet buttons when one of our gateways is selected.
	 *
	 * @returns
	 */
	toggleSubmitButtons: () => {
		const selectedPaymentGatewayId = getElement( '#payment-method input[id*="radio-control-wc-payment-method-options-"]:checked' )?.value;
		removeClass( getElement( '.globalpayments.card-submit' ), 'is-active' );

		if ( params.hide.includes( selectedPaymentGatewayId ) ) {
			helper_functions.hidePlaceOrderButton();
			return;
		}
		if ( ! params.toggle.includes( selectedPaymentGatewayId ) ) {
			helper_functions.showPlaceOrderButton();
			return;
		}

		const submitButtonTarget = getElement( '#' + selectedPaymentGatewayId + '-card-submit' );

		// stored Cards available (registered user selects stored card as payment method)
		const savedCardsAvailable = getElement( helper_functions.getStoredPaymentMethodsRadioSelector() );

		// user selects (new) card as payment method
		const newSavedCardSelected = selectedPaymentGatewayId === getElement( helper_functions.getStoredPaymentMethodsRadioSelector() + ':checked' )?.value;

		// selected payment method is card or digital wallet
		if ( ! savedCardsAvailable || savedCardsAvailable && newSavedCardSelected ) {
			addClass( submitButtonTarget, 'is-active' );
			helper_functions.hidePlaceOrderButton();
		} else {
			// selected payment method is stored card
			hideElement( submitButtonTarget );
			helper_functions.showPlaceOrderButton();
		}
	},

	/**
	 * Hide the default WooCommerce 'Place Order' button.
	 */
	hidePlaceOrderButton: () => {
		hideElement( addClass( getElement( helper_functions.getPlaceOrderButtonSelector() ), 'woocommerce-globalpayments-hidden' ) );
	},

	/**
	 * Show the default WooCommerce 'Place Order' button.
	 */
	showPlaceOrderButton: () => {
		showElement( removeClass( getElement( helper_functions.getPlaceOrderButtonSelector() ), 'woocommerce-globalpayments-hidden' ) );
	},

	/**
	 * Gets the current checkout form
	 *
	 * @returns {Element}
	 */
	getFormSelector: () => {
		return 'form.wc-block-components-form.wc-block-checkout__form';
	},

	createInputElement: ( id, name, value ) => {
		let inputElement = ( document.getElementById( id + '-' + name ) );

		if ( ! inputElement ) {
			inputElement = document.createElement( 'input' );
			inputElement.id = id + '-' + name;
			inputElement.name = id + '[' + name + ']';
			inputElement.type = 'hidden';
			getElement( helper_functions.getFormSelector() ).appendChild( inputElement );
		}

		inputElement.value = value;
	},

	/**
	 * Creates the parent for the submit button
	 *
	 * @returns
	 */
	createSubmitButtonTarget: ( id ) => {
		const el = document.createElement( 'div' );
		el.id = helper_functions.getSubmitButtonTargetSelector( id ).replace( '#', '' );
		el.className = 'globalpayments ' + id + ' card-submit';

		const elem = getElement( helper_functions.getPlaceOrderButtonSelector() );
		elem?.parentNode.insertBefore( el, elem.nextSibling );
		// match the visibility of our payment form
		helper_functions.toggleSubmitButtons( id );
	},

	/**
	 * Places/submits the order to Woocommerce
	 *
	 * Attempts to click the default 'Place Order' button that is used by payment methods.
	 * This is to account for other plugins taking action based on that click event, even
	 * though there are usually better options. If anything fails during that process,
	 * we fall back to calling `this.placeOrder` manually.
	 *
	 * @returns
	 */
	placeOrder: () => {
		try {
			const originalSubmit = getElement( helper_functions.getPlaceOrderButtonSelector() );
			if ( originalSubmit ) {
				originalSubmit.click();
				return;
			}
		} catch ( e ) {
			/* om nom nom */
		}

		getElement( helper_functions.getFormSelector() ).submit();
	},

	/**
	 * Shows payment error and scrolls to it
	 *
	 * @param {string} message Error message
	 *
	 * @returns
	 */
	showPaymentError: ( message ) => {
		const form = getElement( helper_functions.getFormSelector() );

		// Remove notices from all sources
		getElement( '.woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-globalpayments-checkout-error' )?.remove();

		if ( -1 === message.indexOf( 'woocommerce-error' ) ) {
			message = '<ul class="woocommerce-error"><li>' + message + '</li></ul>';
		}
		form?.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-globalpayments-checkout-error">' + message + '</div>' );

		getElement( 'html, body' ).animate( {
			scrollTop: ( form.offsetTop - 100 )
		}, 1000 );

		helper_functions.unblockOnError();

		document.body.dispatchEvent( new Event( 'checkout_error' ) );
	},

	/**
	 * Blocks checkout UI
	 *
	 * Implementation pulled from `woocommerce/assets/js/frontend/checkout.js`
	 *
	 * @returns
	 */
	blockOnSubmit: () => {
		const $form = getElement( helper_functions.getFormSelector() );
		const form_data = $form.data?.();
		if ( 1 !== form_data?.['blockUI.isBlocked'] ) {
			$form.block?.(
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
	unblockOnError: () => {
		getElement( helper_functions.getFormSelector() )?.unblock?.();
	},
	hidePaymentMethod: ( id ) => {
		const paymentMethods = getPaymentMethods();
		delete paymentMethods[ id ];

		window.wp.data.dispatch( window.wc.wcBlocksData.PAYMENT_STORE_KEY).__internalRemoveAvailablePaymentMethod( id );
	},
	/**
	 * Note: if 'setPaymentMethodData' is called multiple times will override previous data
	 * thus we call it once with all the data
	 */
	setPaymentMethodData: ( data ) => {
		window.wp.data.dispatch( window.wc.wcBlocksData.PAYMENT_STORE_KEY ).__internalSetPaymentMethodData( data );
	},
	dispatchInfo: ( { message, context = 'gp-wp-context', cb = null } ) => {
		window.wp.data.dispatch( 'core/notices' ).createInfoNotice( message, {
			context,
		} );

		cb?.();
	},
	dispatchError: ( { message, context = 'gp-wp-context', cb = null } ) => {
		window.wp.data.dispatch( 'core/notices' ).createErrorNotice( message, {
			context,
		} );

		cb?.();
	},
	hasValidationErrors: () => {
		return window.wp.data.select( window.wc.wcBlocksData.VALIDATION_STORE_KEY ).hasValidationErrors();
	}
};
