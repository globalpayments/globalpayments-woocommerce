import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from '../helper';
import { useEffect } from 'react';

const { __ } = wp.i18n;

export const AffirmComponent = ( props ) => {
	const { id, eventRegistration } = props;
	const { onPaymentSetup } = eventRegistration;
	const settings = getSetting( id + '_data', {} );
	const helper = gp_helper( settings.helper_params );
	helper.showPlaceOrderButton();

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			const shippingPhone = document.getElementById( 'shipping-phone' );
			const billingPhone = document.getElementById( 'billing-phone' );
			if ( ! shippingPhone?.value && ! billingPhone?.value ) {
				return {
					type: 'error',
					message: __( '<strong>Phone</strong> is a required field for this payment method.', 'globalpayments-gateway-provider-for-woocommerce' )
				};
			}

			return true;
		} );

		return () => {
			unsubscribe();
		};
	}, [ onPaymentSetup ] );
};
