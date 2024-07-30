import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from '../../components/helper';
import { useEffect } from 'react';

const state = {
	order: null,
	settings: null,
	paymentsClient: null,
};

let helper = {};

export const GooglePayComponent = ( props ) => {
	const { eventRegistration } = props;

	state.settings = getSetting( 'globalpayments_googlepay_data', {} );

	helper = gp_helper( state.settings.helper_params );

	const { onCheckoutFail } = eventRegistration;

	useEffect( () => {
		attachEventHandlers();

		const unsubscribeOnCheckoutFail = onCheckoutFail( () => {
			document.querySelector( '.is-error' )?.scrollIntoView( {
				behavior: 'smooth',
				block: 'center',
				inline: 'start',
			} );
		} );

		return () => {
			if ( ! document.querySelector( helper.getPaymentMethodRadioSelector( state.settings.id ) )?.checked ) {
				removeGooglePayButton();
				helper.toggleSubmitButtons();
			}

			unsubscribeOnCheckoutFail();
		};
	}, [ onCheckoutFail ] );
};

const attachEventHandlers = () => {
	if ( helper.isFirstPaymentMethod( state.settings.id ) || helper.isOnlyGatewayMethodDisplayed( state.settings.id ) ) {
		window.onload = () => {
			initialize();
		};
	}

	document.querySelector( '#radio-control-wc-payment-method-options-globalpayments_googlepay' ).addEventListener( 'change', initialize );
};

const initialize = () => {
	setGooglePaymentsClient();

	state.paymentsClient.isReadyToPay(
		getGoogleIsReadyToPayRequest()
	).then( ( response ) => {
		if ( response.result ) {
			addGooglePayButton( state.settings.id );
		} else {
			helper.hidePaymentMethod( state.settings.id );
			helper.hidePlaceOrderButton();
		}
	} ).catch( console.error );
};

const setGooglePaymentsClient = () => {
	if ( null === state.paymentsClient ) {
		state.paymentsClient = new google.payments.api.PaymentsClient( {
			environment: getEnvironment()
		} );
	}
};

const getEnvironment = () => {
	return state.settings.payment_method_options.env;
};

const getGoogleIsReadyToPayRequest = () => {
	return Object.assign(
		{},
		getBaseRequest(),
		{
			allowedPaymentMethods: [ getBaseCardPaymentMethod() ]
		}
	);
};

const getBaseRequest = () => {
	return {
		apiVersion: 2,
		apiVersionMinor: 0
	};
};

const getBaseCardPaymentMethod = () => {
	return {
		type: 'CARD',
		parameters: {
			allowedAuthMethods: getAllowedCardAuthMethods(),
			allowedCardNetworks: getAllowedCardNetworks(),
			billingAddressRequired: true
		}
	};
};

const getAllowedCardAuthMethods = () => {
	return state.settings.payment_method_options.aca_methods;

};
const getAllowedCardNetworks = () => {
	return state.settings.payment_method_options.cc_types;
};

const addGooglePayButton = () => {
	helper.createSubmitButtonTarget( state.settings.id );

	const button = state.paymentsClient.createButton( {
		buttonColor: getBtnColor(),
		onClick: () => {
			onGooglePaymentButtonClicked();
		}
	} );

	document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) ).append( button );
};

const getBtnColor = () => {
	return state.settings.payment_method_options.button_color;
};

const onGooglePaymentButtonClicked = () => {
	if ( helper.hasValidationErrors() ) {
		window.wp.data.dispatch( window.wc.wcBlocksData.VALIDATION_STORE_KEY).showAllValidationErrors();

		document.querySelector( '.has-error' )?.scrollIntoView( {
			behavior: 'smooth',
			block: 'center',
			inline: 'start',
		} );

		return;
	}

	helper.getOrderInfo();

	state.order = helper.order;
	const paymentDataRequest = getGooglePaymentDataRequest();

	state.paymentsClient.loadPaymentData( paymentDataRequest ).then( ( paymentData ) => {
		helper.setPaymentMethodData( {
			dw_token: JSON.stringify( JSON.parse( paymentData.paymentMethodData.tokenizationData.token ) ),
			cardHolderName: paymentData.paymentMethodData.info.billingAddress.name
		} );

		return helper.placeOrder();
	} ).catch( console.error );
};

const getGooglePaymentDataRequest = () => {
	const paymentDataRequest = Object.assign({}, getBaseRequest() );
	paymentDataRequest.allowedPaymentMethods = [ getCardPaymentMethod() ];
	paymentDataRequest.transactionInfo = getGoogleTransactionInfo();
	paymentDataRequest.merchantInfo = {
		merchantId: getGoogleMerchantId(),
		merchantName: getGoogleMerchantName()
	};

	return paymentDataRequest;
};

const getCardPaymentMethod = () => {
	return Object.assign(
		{},
		getBaseCardPaymentMethod(),
		{
			tokenizationSpecification: getTokenizationSpecification()
		}
	);
};

const getTokenizationSpecification = () => {
	return {
		type: 'PAYMENT_GATEWAY',
		parameters: {
			gateway: 'globalpayments',
			gatewayMerchantId: state.settings.payment_method_options.global_payments_merchant_id
		}
	};
};

const getGoogleTransactionInfo = () => {
	return {
		totalPriceStatus: 'FINAL',
		totalPrice: state.order.amount.toString(),
		currencyCode: state.order.currency
	};
};

const getGoogleMerchantId = () => {
	return state.settings.payment_method_options.google_merchant_id;
};

const getGoogleMerchantName = () => {
	return state.settings.payment_method_options.google_merchant_name;
};

const removeGooglePayButton = () => {
	document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) )?.remove();
};
