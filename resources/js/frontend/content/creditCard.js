import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from "../components/helper";
import { useEffect } from 'react';
import { threeDSecure } from '../components/threeDSecureComponent';

const state = {
	settings: null,
	cardForm: null,
	tokenResponse: null,
	fieldOptions: null,
	serverTransId: null,
};

let helper = {};
export const CreditCard = ( props ) => {
	const { id, eventRegistration } = props;

	const settings = getSetting( id + '_data', {} );
	const paymentFields = Object.entries( settings.secure_payment_fields );
	const { StoreNoticesContainer } = window.wc.blocksCheckout;

	helper = gp_helper( settings.helper_params );

	state.settings = settings;
	state.fieldOptions = settings.secure_payment_fields;
	state.threedsecure = settings.threedsecure;

	const { onCheckoutFail } = eventRegistration;

	useEffect( () => {
		attachEventHandlers();

		const unsubscribe = onCheckoutFail( () => {
			unblockFormElements();
			removeSpinnerFromSubmitButton();
		} );

		return () => {
			if ( ! document.querySelector( helper.getPaymentMethodRadioSelector( state.settings.id ) )?.checked ) {
				document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) )?.remove();
				helper.showPlaceOrderButton();
			}

			unsubscribe();
		};
	}, [ onCheckoutFail ] );

	return (
		<>
			<div dangerouslySetInnerHTML={{ __html: state.settings.environment_indicator }} />
			{
				paymentFields.map( ( field ) =>
					<PaymentField id={ id } field={ field[1] } key={ field[0] } />
				)
			}
			<StoreNoticesContainer context="gp-wp-context" />
		</>
	);
};

const PaymentField = ( props ) => {
	const { id, field } = props;

	return (
		<div className={ `globalpayments ${id} ${field.class}` }>
			<label htmlFor={ `${id}-${field.class}` }>
				{ field.label }
				<span className="required">*</span>
			</label>
			<div id={`${id}-${field.class}`}></div>
		</div>
	);
};
var initRetryCount = 0;
var isFormInitialized = false;
const MAX_RETRY_COUNT = 20;
const attachEventHandlers = () => {
	// General
	document.querySelector( '#order_review, #add_payment_method' )?.addEventListener( 'click', ( e ) => {
			if ( e.target.matches( '#payment-method input[type="radio"]' ) ) {
				helper.toggleSubmitButtons();
			}
		}
	);

	document.body.addEventListener( 'checkout_error', () => {
		document.querySelector('#globalpayments_gpapi-serverTransId')?.remove();
	} );


	// Admin Pay for Order
	document.addEventListener( 'globalpayments_pay_order_modal_loaded', renderPaymentFields );
	document.addEventListener( 'globalpayments_pay_order_modal_error', ( event, message ) => {
		helper.showPaymentError( message );
	} );

		// Initialize payment form when the payment method is first/only
	// or when document is ready, unminified JS from the minified gateways.js taken from v1.4.6
    const initializeForm = () => {
        // Try immediate initialization first
        renderPaymentFields();

        // If form isn't initialized yet, try again with delays
        if (!isFormInitialized) {
            setTimeout(() => {
                if (!isFormInitialized) renderPaymentFields();
            }, 500);

            setTimeout(() => {
                if (!isFormInitialized) renderPaymentFields();
            }, 1000);
        }
    };

    if ( helper.isFirstPaymentMethod( state.settings.id ) || helper.isOnlyGatewayMethodDisplayed( state.settings.id ) ) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeForm);
            window.addEventListener('load', initializeForm);
        } else if (document.readyState === 'interactive') {
            setTimeout(initializeForm, 100);
            window.addEventListener('load', initializeForm);
        } else {
            // Document is already complete
            initializeForm();
        }
    } else {
        // console.log('Not the first payment method, will wait for user selection');
    }
	// Add event listener for payment method change
    const radioElement = document.querySelector(
        "#radio-control-wc-payment-method-options-globalpayments_gpapi"
    );
	 const handlePaymentMethodChange = () => {
        console.log('Payment method changed to GlobalPayments');
        // Reset initialization state when payment method changes
        isFormInitialized = false;
        initRetryCount = 0;
        renderPaymentFields();
    };

    if (radioElement) {
        radioElement.addEventListener("change", handlePaymentMethodChange);
    } else {
        // If radio element doesn't exist yet, try again after a delay
        setTimeout(() => {
            const delayedRadioElement = document.querySelector(
                "#radio-control-wc-payment-method-options-globalpayments_gpapi"
            );
            if (delayedRadioElement) {
                delayedRadioElement.addEventListener("change", handlePaymentMethodChange);
            }
        }, 500);
    }
};

const renderPaymentFields = () => {
	if (isFormInitialized && state.cardForm) {
        console.log('GlobalPayments form is already initialized, skipping...');
        return;
    }

    // Check retry limit
    if (initRetryCount >= MAX_RETRY_COUNT) {
        console.error('Maximum retry attempts reached. GlobalPayments form initialization failed.');
        dispatchError('Payment form could not be loaded. Please refresh the page and try again.');
        return;
    }
	const gatewayConfig = state.settings.gateway_options;
	if ( gatewayConfig.error ) {
		dispatchError( gatewayConfig.message );
	}

	// ensure the submit button's parent is on the page as this is added
	// only after the initial page load
	if ( ! document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) ) ) {
		helper.createSubmitButtonTarget( state.settings.id );
	}

	const globalPayments = GlobalPayments;
	globalPayments.configure( gatewayConfig );
	globalPayments.on( 'error', handleErrors);

	state.cardForm = globalPayments.ui.form(
		{
			fields: getFieldConfiguration(),
			styles: getStyleConfiguration()
		}
	);

	state.cardForm.on( 'submit', 'click', () => {
		addSpinnerToSubmitButton();
		blockFormElements();

		helper.blockOnSubmit();
	} );
	state.cardForm.on( 'token-success', handleResponse );
	state.cardForm.on( 'token-error', handleErrors );
	state.cardForm.on( 'error', handleErrors );
	state.cardForm.on( 'card-form-validity', ( isValid ) => {
		if ( !isValid ) {
			helper.unblockOnError();
			removeSpinnerFromSubmitButton();
			unblockFormElements();
		}
	} );

	// match the visibility of our payment form
	state.cardForm.ready( () => {
		helper.toggleSubmitButtons();
	} );
};

const addSpinnerToSubmitButton = () => {
	const el = document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) );
	el.classList.add('wc-block-components-spinner');
	el.classList.add('wc-block-components-checkout-step--disabled');
	el.style.height = 'initial !important';
	el.style.width = 'initial !important';
	el.style.position = 'relative';
};

const removeSpinnerFromSubmitButton = () => {
	const el = document.querySelector( helper.getSubmitButtonTargetSelector( state.settings.id ) );
	el.classList.remove( 'wc-block-components-spinner' );
	el.classList.remove( 'wc-block-components-checkout-step--disabled' );
	el.style.height = 'initial !important';
	el.style.width = 'initial !important';
	el.style.position = 'relative';
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

const dispatchError = ( message ) => {
	helper.dispatchError( {
		message,
		cb: () => {
			removeSpinnerFromSubmitButton();
			unblockFormElements();
		}
	} );
};

const showValidationError = ( fieldType ) => {
	dispatchError( state.settings.secure_payment_fields[fieldType].messages.validation );

	helper.unblockOnError();
};

const handleErrors = ( error ) => {
	if ( ! error.reasons ) {
		dispatchError( 'Something went wrong. Please contact us to get assistance.' );

		return;
	}

	for ( let i = 0; i < error.reasons.length; i++ ) {
		const reason = error.reasons[i];
		switch ( reason.code ) {
			case 'NOT_AUTHENTICATED':
				dispatchError( 'We\'re not able to process this payment. Please refresh the page and try again.' );

				break;
			default:
				dispatchError( reason.message );
		}
	}
};

const getFieldConfiguration = () => {
	const fields = {
		'card-number': {
			placeholder: state.fieldOptions['card-number-field'].placeholder,
			target: '#' + state.settings.id + '-' + state.fieldOptions['card-number-field'].class
		},
		'card-expiration': {
			placeholder: state.fieldOptions['card-expiry-field'].placeholder,
			target: '#' + state.settings.id + '-' + state.fieldOptions['card-expiry-field'].class
		},
		'card-cvv': {
			placeholder: state.fieldOptions['card-cvc-field'].placeholder,
			target: '#' + state.settings.id + '-' + state.fieldOptions['card-cvc-field'].class
		},
		'submit': {
			text: getSubmitButtonText(),
			target: '#' + state.settings.id + '-card-submit'
		}
	};
	if ( state.fieldOptions.hasOwnProperty( 'card-holder-name-field' ) ) {
		fields["card-holder-name"] = {
			placeholder: state.fieldOptions['card-holder-name-field'].placeholder,
			target: '#' + state.settings.id + '-' + state.fieldOptions['card-holder-name-field'].class
		}
	}
	return fields;
};

const getSubmitButtonText = () => {
	const selector = document.querySelector( helper.getPlaceOrderButtonSelector() );
	return selector.innerText;
};

const getStyleConfiguration = () => {
	return JSON.parse( state.settings.field_styles );
};

const handleResponse = ( response ) => {
	if ( helper.hasValidationErrors() ) {
		removeSpinnerFromSubmitButton();
		unblockFormElements();

		window.wp.data.dispatch( window.wc.wcBlocksData.VALIDATION_STORE_KEY).showAllValidationErrors();

		document.querySelector( '.has-error' )?.scrollIntoView( {
			behavior: 'smooth',
			block: 'center',
			inline: 'start',
		} );

		return;
	}

	if ( ! validateTokenResponse( response ) ) {
		return;
	}

	state.tokenResponse = response;

	handlePlaceOrder();
};

const handlePlaceOrder = () => {
	if ( state.settings.gateway_options.enableThreeDSecure ) {
		helper.getOrderInfo();

		threeDSecure( {
			state,
			helper,
			dispatchError,
			placeOrder
		} );
	} else {
		placeOrder();
	}
};

const placeOrder = () => {
	if (typeof state.cardForm.frames[ "card-cvv" ].getCvv === 'function' ) {
		state.cardForm.frames[ "card-cvv" ].getCvv().then( ( cvvVal ) => {

			/**
			 * CVV; needed for TransIT gateway processing only
			 *
			 * @type {string}
			 */
			if ( state.tokenResponse ) {
				state.tokenResponse.details.cardSecurityCode = cvvVal;
			}

			useTokenToPlaceOrder();
		});
	} else {
		useTokenToPlaceOrder();
	}
}

const validateTokenResponse = ( response ) => {
	if ( response.details ) {
		const expirationDate = new Date( response.details.expiryYear, response.details.expiryMonth - 1 );
		const now = new Date();
		const thisMonth = new Date( now.getFullYear(), now.getMonth() );

		if ( ! response.details.expiryYear || ! response.details.expiryMonth || expirationDate < thisMonth ) {
			showValidationError( 'card-expiry-field' );
			return false;
		}
	}

	if ( response.details && ! response.details.cardSecurityCode ) {
		showValidationError( 'card-cvc-field' );
		return false;
	}

	return true;
};

const useTokenToPlaceOrder = () => {
	const paymentMethodData = {
		token_response: JSON.stringify( state.tokenResponse ),
	};

	if ( state.serverTransId ) {
		paymentMethodData.serverTransId = state.serverTransId
	}

	helper.setPaymentMethodData( paymentMethodData );

	helper.placeOrder();
};
