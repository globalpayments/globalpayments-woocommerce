import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from "../components/helper";
import { useEffect } from 'react';
import { threeDSecure } from './threeDSecureComponent';

const state = {
	settings: null,
	onSubmit: null,
	serverTransId: '',
};

let helper = {};
export const SavedTokenComponent = ( props ) => {
	const { id, onSubmit, eventRegistration } = props;
	state.onSubmit = onSubmit;

	const settings = getSetting( id + '_data', {} );
	helper = gp_helper( settings.helper_params );

	state.settings = settings;
	state.threedsecure = settings.threedsecure;

	const { onCheckoutFail } = eventRegistration;

	useEffect( () => {
		window.onload = () => {
			attachEventHandlers();
		};

		const unsubscribeOnCheckoutFail = onCheckoutFail( () => {
			unblockFormElements();
			removeSpinnerFromPlaceOrderButton();
		} );

		return () => {
			unsubscribeOnCheckoutFail();
		};
	}, [ onCheckoutFail ] );

	const { StoreNoticesContainer } = window.wc.blocksCheckout;

	return <StoreNoticesContainer context="gp-wp-context" />;
};

const attachEventHandlers = () => {
	// When clicked check if the selected payment method is a stored one then handle placing order
	document.querySelector( helper.getPlaceOrderButtonSelector() ).addEventListener( 'click', ( e ) => {
		if ( document.querySelector( helper.getStoredPaymentMethodsRadioSelector() ) ) {
			e.preventDefault();
			e.stopImmediatePropagation();

			handlePlaceOrder();
		}
	} );
};

const addSpinnerToPlaceOrderButton = () => {
	const el = document.querySelector( helper.getPlaceOrderButtonSelector() );
	const span = document.createElement( 'span' );
	span.id = 'wc-block-components-spinner';
	span.classList.add('wc-block-components-spinner');
	el.appendChild( span );
};

const removeSpinnerFromPlaceOrderButton = () => {
	document.querySelector( '#wc-block-components-spinner' )?.remove();
};

const blockFormElements = () => {
	document.querySelector( helper.getFormSelector() ).classList.add( 'wc-block-components-checkout-step--disabled' );
	document.querySelectorAll( `${ helper.getFormSelector() } > *` ).forEach( ( input ) => {
		input.style.pointerEvents = 'none';
	});
};
const unblockFormElements = () => {
	document.querySelector( helper.getFormSelector() ).classList.remove( 'wc-block-components-checkout-step--disabled' );
	document.querySelectorAll( `${ helper.getFormSelector() } > *` ).forEach( ( input ) => {
		input.style.pointerEvents = '';
	});
};

const dispatchInfo = ( message ) => {
	helper.dispatchInfo( {
		message,
		cb: () => {
			removeSpinnerFromPlaceOrderButton();
			unblockFormElements();
		}
	} );
};

const dispatchError = ( message ) => {
	helper.dispatchError( {
		message,
		cb: () => {
			removeSpinnerFromPlaceOrderButton();
			unblockFormElements();
		}
	} );
};

const handlePlaceOrder = () => {
	if ( helper.hasValidationErrors() ) {
		removeSpinnerFromPlaceOrderButton();
		unblockFormElements();

		document.querySelector( '.has-error' ).scrollIntoView( {
			behavior: 'smooth',
			block: 'center',
			inline: 'start',
		} );

		return;
	}

	if ( state.settings.gateway_options.enableThreeDSecure ) {
		addSpinnerToPlaceOrderButton();
		blockFormElements();
		threeDSecure( {
			state,
			helper,
			dispatchError,
			dispatchInfo,
			placeOrder: () => {
				const paymentMethodData = window.wp.data.select( window.wc.wcBlocksData.PAYMENT_STORE_KEY ).getPaymentMethodData();
				paymentMethodData.serverTransId = state.serverTransId;

				helper.setPaymentMethodData( paymentMethodData );

				state.onSubmit();
			}
		} );
	} else {
		state.onSubmit();
	}
};
