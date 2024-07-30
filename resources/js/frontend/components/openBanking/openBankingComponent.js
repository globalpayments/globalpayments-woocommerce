import { getSetting } from '@woocommerce/settings';
import { helper as gp_helper } from '../helper';

export const OpenBankingComponent = ( { id } ) => {
	const settings = getSetting( id + '_data', {} );
	const helper = gp_helper( settings.helper_params );
	helper.showPlaceOrderButton();
};
