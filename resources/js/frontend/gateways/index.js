import { register } from '../utils/register';
import { gpapiPaymentMethods } from './gpapi';
import { getPaymentMethods } from '@woocommerce/blocks-registry';

const paymentMethods = getPaymentMethods();

gpapiPaymentMethods.forEach( ( paymentMethodProps ) => {
	if ( ! paymentMethods.hasOwnProperty( paymentMethodProps.id ) ) {
		register( paymentMethodProps );
	}
} );
