const isDifferentShippingAddress = () => {
	return document.querySelector( '.wc-block-checkout__use-address-for-billing input[type="checkbox"]' )?.checked;
};

/**
 * Get checkout billing address
 *
 * @returns {{country: *, city: *, postalCode: *, streetAddress1: *, streetAddress2: *, state: *}}
 */
const getBillingAddressFormData = () => {
	return {
		streetAddress1: document.querySelector( '#billing-address_1' )?.value,
		streetAddress2: document.querySelector( '#billing-address_2' )?.value,
		city: document.querySelector( '#billing-city' )?.value,
		state: document.querySelector( '#billing-state input' )?.value,
		postalCode: document.querySelector( '#billing-postcode' )?.value,
		country: document.querySelector( '#billing-country input' )?.value,
	};
};

/**
 * Get checkout shipping address
 *
 * @returns {{country, city, postalCode, streetAddress1, streetAddress2, state}}
 */
const getShippingAddressFormData = () => {
	return {
		streetAddress1: document.querySelector( '#shipping-address_1' )?.value,
		streetAddress2: document.querySelector( '#shipping-address_2' )?.value,
		city: document.querySelector( '#shipping-city' )?.value,
		state: document.querySelector( '#shipping-state input' )?.value,
		postalCode: document.querySelector( '#shipping-postcode' )?.value,
		country: document.querySelector( '#shipping-country input' )?.value,
	};
};

/**
 * Assists with notifying the challenge status, when the user closes the challenge window
 */
const cancelTransaction = () => {
	window.parent.postMessage( { data: { "transStatus":"N" }, event: "challengeNotification" }, window.location.origin );
};

/**
 * 3DS Process
 */
export const threeDSecure = ( { state, helper, dispatchError, dispatchInfo, placeOrder } ) => {
	helper.blockOnSubmit();
	state.settings.helper_params.order = helper.order;

	const tokenResponse = state.tokenResponse ? JSON.stringify( state.tokenResponse ) : null;
	const wcTokenId = document.querySelector( helper.getStoredPaymentMethodsRadioSelector() )?.value ?? 'new';

	GlobalPayments.ThreeDSecure.checkVersion( state.threedsecure.checkEnrollmentUrl, {
		tokenResponse,
		wcTokenId,
		order: {
			id: state.settings.helper_params.order.id,
			amount: state.settings.helper_params.order.amount,
			currency: state.settings.helper_params.order.currency,
		}
	})
	.then( ( versionCheckData ) => {
		if ( versionCheckData.error ) {
			dispatchError( versionCheckData.message );
			return false;
		}

		if ( "NOT_ENROLLED" === versionCheckData.status && "YES" !== versionCheckData.liabilityShift ) {
			dispatchError( 'Please try again with another card.' );

			return false;
		}

		if ( "NOT_ENROLLED" === versionCheckData.status && "YES" === versionCheckData.liabilityShift ) {
			placeOrder();

			return true;
		}

		const addressMatch = ! isDifferentShippingAddress();
		const billingAddress = getBillingAddressFormData();
		const shippingAddress = addressMatch ? billingAddress : getShippingAddressFormData();

		GlobalPayments.ThreeDSecure.initiateAuthentication( state.threedsecure.initiateAuthenticationUrl, {
			tokenResponse,
			wcTokenId,
			versionCheckData: versionCheckData,
			challengeWindow: {
				windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
				displayMode: 'lightbox',
			},
			order: {
				id: state.settings.helper_params.order.id,
				amount: state.settings.helper_params.order.amount,
				currency: state.settings.helper_params.order.currency,
				billingAddress: billingAddress,
				shippingAddress: shippingAddress,
				addressMatchIndicator: addressMatch,
				customerEmail: document.querySelector( '.wc-block-components-address-form__email input#email' ).value,
			}
		})
		.then( ( authenticationData ) => {
			if ( authenticationData.error ) {
				dispatchError( authenticationData.message );

				return false;
			}

			const serverTransId = authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID || versionCheckData.serverTransactionId;
			helper.createInputElement( state.settings.id, 'serverTransId', serverTransId );

			state.serverTransId = serverTransId;

			placeOrder();

			return true;
		})
		.catch( ( error ) => {
			console.error( error );
			console.error( error.reasons );

			dispatchError( 'Something went wrong while doing 3DS processing.' );

			return false;
		});
	})
	.catch( ( error ) => {
		console.error( error );
		console.error( error.reasons );

		dispatchError( 'Something went wrong while doing 3DS processing.' );

		return false;
	});


	document.addEventListener( 'click', ( e ) => {
		if ( e.target.matches( 'img[id^="GlobalPayments-frame-close-"]' ) ) {
			cancelTransaction();
		}
	} );

	return false;
};
