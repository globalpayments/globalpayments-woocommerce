import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from '../../components/helper';
import { useEffect } from 'react';

const { __ } = wp.i18n;

const state = {
	order: null,
	ctpForm: null,
	settings: null,
};

let helper = {};

export const ClickToPayComponent = () => {
	state.settings = getSetting( 'globalpayments_clicktopay_data', {} );
	helper = gp_helper( state.settings.helper_params );

	useEffect( () => {
		attachEventHandlers();

		return () => {
			if ( ! document.querySelector( helper.getPaymentMethodRadioSelector( state.settings.id ) )?.checked ) {
				helper.toggleSubmitButtons();
			}
		};
	} );

	return (
		<>
			<div id={ state.settings.id }></div>
		</>
	);
};

const attachEventHandlers = () => {
	if ( helper.isFirstPaymentMethod( state.settings.id ) || helper.isOnlyGatewayMethodDisplayed( state.settings.id ) ) {
		window.onload = () => {
			renderClickToPay();
		};
	}

	document.querySelector( '#radio-control-wc-payment-method-options-globalpayments_clicktopay' ).addEventListener( 'change', renderClickToPay );
};

/**
 * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
 * Global Payments' production servers.
 *
 * @returns
 */
const renderClickToPay = () => {
	helper.toggleSubmitButtons();

	clearContent();

	if ( ! GlobalPayments.configure ) {
		console.log( __( 'Warning! Payment fields cannot be loaded', 'globalpayments-gateway-provider-for-woocommerce' ) );
		return;
	}

	const gatewayConfig = state.settings.payment_method_options;

	helper.getOrderInfo();

	state.order = helper.order;
	gatewayConfig.apms.currencyCode = state.order.currency;

	GlobalPayments.configure( gatewayConfig );
	GlobalPayments.on( 'error', handleErrors );

	state.ctpForm = GlobalPayments.apm.form( '#' + state.settings.id, {
		amount: state.order.amount.toString(),
		style: "gp-default",
		apms: [ GlobalPayments.enums.Apm.ClickToPay ]
	} );

	state.ctpForm.on( 'token-success', handleResponse );

	state.ctpForm.on( 'token-error', handleErrors );
	state.ctpForm.on( 'error', handleErrors );
};

/**
 * If the CTP element already has some previous content, clear it.
 */
const clearContent = () => {
	const ctpElement = document.querySelector( '#' + state.settings.id );
	if ( ctpElement?.children.length > 0 ) {
		while ( ctpElement.firstChild ) {
			ctpElement.removeChild( ctpElement.lastChild );
		}

		document.querySelector( '#' + state.settings.id + '-dw_token' )?.remove();
	}
};

/**
 * Handles errors from the payment field
 *
 * @param {object} error Details about the error
 *
 * @returns
 */
const handleErrors = ( error ) => {
	console.error( error );
};

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
const handleResponse = ( response ) => {
	helper.setPaymentMethodData( {
		dw_token: response.paymentReference
	} );

	return helper.placeOrder();
};
