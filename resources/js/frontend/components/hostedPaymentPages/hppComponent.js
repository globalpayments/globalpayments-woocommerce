import { useEffect } from 'react';

const { __ } = wp.i18n;

/**
 * HPP (Hosted Payment Pages) Component for WooCommerce Blocks
 */
export const HppComponent = ( { 
	billing, 
	shippingData, 
	setValidationErrors, 
	clearValidationErrors, 
	onSubmit, 
	activePaymentMethod,
	eventRegistration,
	emitResponse,
	...props 
} ) => {
	const { onPaymentSetup } = eventRegistration;
	
	useEffect( () => {
		if ( clearValidationErrors ) {
			clearValidationErrors();
		}
	}, [] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			const billingCountry = billing?.billingAddress?.country || '';
			const shippingCountry = shippingData?.shippingAddress?.country || '';
			const billingState = billing?.billingAddress?.state || '';
			const shippingState = shippingData?.shippingAddress?.state || '';
			
			// Validate UK counties
			if ( billingCountry === 'GB' ) {
				if ( ! billingState ) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: __( 'County is required for United Kingdom addresses.', 'globalpayments-gateway-provider-for-woocommerce' ),
					};
				}
			}

			if ( shippingCountry === 'GB' && shippingData?.needsShipping ) {
				if ( ! shippingState ) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: __( 'County is required for United Kingdom shipping addresses.', 'globalpayments-gateway-provider-for-woocommerce' ),
					};
				}
			}

			//Pass the HPP nonce and other data to the backend
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: activePaymentMethod || 'globalpayments_gpapi',
						payment_interface: 'hpp',
						gp_hpp_nonce: props.hpp_nonce || '',
						gateway_id: props.gateway_id || 'globalpayments_gpapi',
						// WooCommerce inbuilt validation will still occour
						checkout_validated: '1',
					},
				},
			};
		} );

		return unsubscribe;
	}, [
		onPaymentSetup,
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
		props.hpp_nonce,
		props.gateway_id,
		activePaymentMethod,
		billing?.billingAddress?.country,
		billing?.billingAddress?.state,
		shippingData?.shippingAddress?.country,
		shippingData?.shippingAddress?.state,
		shippingData?.needsShipping,
	] );

	return (
		<div className="gp-hpp-payment-method">
			<div dangerouslySetInnerHTML={{ __html: props.environment_indicator || '' }} />
			<div className="gp-hpp-description">
				<p>
					{ props.hpp_text || __( 'Pay With Credit / Debit Card Via Globalpayments', 'globalpayments-gateway-provider-for-woocommerce' ) }
				</p>
			</div>
		</div>
	);
};
