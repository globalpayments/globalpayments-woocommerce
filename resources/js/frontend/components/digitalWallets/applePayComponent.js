import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from '../../components/helper';
import { useEffect } from 'react';

const { __ } = wp.i18n;

const state = {
	order: null,
	settings: null,
};

let helper = {};

export const ApplePayComponent = () => {
	state.settings = getSetting( 'globalpayments_applepay_data', {} );

	helper = gp_helper( state.settings.helper_params );

	useEffect( () => {
		attachEventHandlers();

		return () => {
			if ( ! document.querySelector( helper.getPaymentMethodRadioSelector( state.settings.id ) )?.checked ) {
				removeApplePayButton();
				helper.toggleSubmitButtons();
			}
		};
	} );
};

const attachEventHandlers = () => {
	if ( helper.isFirstPaymentMethod( state.settings.id ) || helper.isOnlyGatewayMethodDisplayed( state.settings.id ) ) {
		window.onload = () => {
			initialize();
		};
	}

	document.querySelector( '#radio-control-wc-payment-method-options-globalpayments_applepay' ).addEventListener( 'change', initialize );
};

const initialize = () => {
	helper.toggleSubmitButtons();

	addApplePayButton();
};

/**
 * Add the apple pay button to the DOM
 */
const addApplePayButton = () => {
	helper.createSubmitButtonTarget( state.settings.id );

	const paymentButton = document.createElement( 'div' );
	paymentButton.className = 'apple-pay-button apple-pay-button-' + state.settings.payment_method_options.button_color;
	paymentButton.title = __( 'Pay with Apple Pay', 'globalpayments-gateway-provider-for-woocommerce');
	paymentButton.alt = __( 'Pay with Apple Pay', 'globalpayments-gateway-provider-for-woocommerce');
	paymentButton.id = state.settings.id;

	paymentButton.addEventListener( 'click', ( e ) => {
		e.preventDefault();

		if ( helper.hasValidationErrors() ) {
			window.wp.data.dispatch( window.wc.wcBlocksData.VALIDATION_STORE_KEY).showAllValidationErrors();

			document.querySelector( '.has-error' )?.scrollIntoView( {
				behavior: 'smooth',
				block: 'center',
				inline: 'start',
			} );

			return;
		}

		const applePaySession = createApplePaySession();
		applePaySession.begin();
	} );

	document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) ).append( paymentButton );
};

const createApplePaySession = () => {
	helper.getOrderInfo();

	state.order = helper.order;
	let applePaySession = null;

	try {
		applePaySession = new ApplePaySession( 1, getPaymentRequest() );
	} catch ( err ) {
		console.error( __( 'Unable to create ApplePaySession', 'globalpayments-gateway-provider-for-woocommerce' ), err )
		alert( __( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		return false;
	}

	// Handle validate merchant event
	applePaySession.onvalidatemerchant = ( event ) => {
		onApplePayValidateMerchant( event, applePaySession );
	}

	// Attach payment auth event
	applePaySession.onpaymentauthorized = ( event ) => {
		onApplePayPaymentAuthorize( event, applePaySession )
	}

	applePaySession.oncancel = ( event ) => {
		alert( __( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
	};

	return applePaySession;
};

const getPaymentRequest = () => {
	return {
		countryCode: getCountryId(),
		currencyCode: state.order.currency,
		merchantCapabilities: [
			'supports3DS'
		],
		supportedNetworks: getAllowedCardNetworks(),
		total: {
			label: getDisplayName(),
			amount: state.order.amount.toString()
		},
		requiredBillingContactFields: [ 'postalAddress', 'name' ],
	};
};

const getCountryId = () => {
	return state.settings.payment_method_options.country_code;
};

const getAllowedCardNetworks = () => {
	return state.settings.payment_method_options.cc_types;
};

const getDisplayName = () => {
	return state.settings.payment_method_options.apple_merchant_display_name;
};

const onApplePayValidateMerchant = ( event, session ) => {
	fetch( state.settings.payment_method_options.validate_merchant_url, {
		cache: 'no-store',
		body: JSON.stringify( { 'validationUrl': event.validationURL } ),
		method: 'POST'
	} ).then( ( response ) => response.json() )
	.then( ( response ) => {
		if ( response.error ) {
			console.log( 'response', response );
			session.abort();
			alert( __( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		} else {
			session.completeMerchantValidation( JSON.parse( response.message ) );
		}
	} ).catch( ( response ) => {
		console.log( 'response', response );
		session.abort();
		alert( __( 'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.', 'globalpayments-gateway-provider-for-woocommerce' ) );
	} );
};

const onApplePayPaymentAuthorize = ( event, session ) => {
	try {
		const paymentMethodData = {
			dw_token: JSON.stringify( event.payment.token.paymentData )
		};

		if ( event.payment.billingContact ) {
			paymentMethodData.cardHolderName = event.payment.billingContact.givenName + ' ' +  event.payment.billingContact.familyName;
		}

		helper.setPaymentMethodData( paymentMethodData );

		const originalSubmit = document.querySelector( helper.getPlaceOrderButtonSelector() );
		if ( originalSubmit ) {
			originalSubmit.click();
			session.completePayment( ApplePaySession.STATUS_SUCCESS );
			return;
		}
	} catch ( e ) {
		session.completePayment( ApplePaySession.STATUS_FAILURE );
	}
	session.completePayment( ApplePaySession.STATUS_SUCCESS );
	document.querySelector( helper.getFormSelector() ).submit();
};

const removeApplePayButton = () => {
	document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) )?.remove();
};
